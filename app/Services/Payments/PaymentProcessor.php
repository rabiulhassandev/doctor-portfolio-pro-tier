<?php

namespace App\Services\Payments;

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Booking\AppointmentWorkflow;
use App\Services\Notifications\AppointmentNotifier;
use App\Support\Payments\PaymentResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies a verified payment result.
 *
 * ===========================================================================
 * EVERY CALLBACK COMES THROUGH HERE, AND IT MUST BE IDEMPOTENT
 * ===========================================================================
 *
 * A single successful payment reaches us at least twice: once when the
 * patient's browser returns to the success URL, and again as an IPN posted from
 * the gateway's own servers. IPNs can also be retried. Without the short-circuit
 * below, the patient would get two receipts and the appointment would be
 * "confirmed" twice — which, since confirming is a one-way transition, would
 * throw the second time.
 *
 * The row is locked for the duration so two callbacks arriving in the same
 * second cannot both pass the check.
 */
final class PaymentProcessor
{
    public function __construct(
        private readonly AppointmentWorkflow $workflow,
        private readonly AppointmentNotifier $notifier,
    ) {}

    /**
     * Record the outcome, and confirm the appointment if the money arrived.
     */
    public function apply(PaymentResult $result): ?Payment
    {
        $outcome = DB::transaction(function () use ($result): array {
            $payment = Payment::query()
                ->where('reference', $result->reference)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                Log::warning('A payment result arrived for an unknown reference.', [
                    'reference' => $result->reference,
                ]);

                return ['payment' => null, 'newlyPaid' => false];
            }

            // Already handled — do nothing, and above all send nothing.
            if ($payment->status === PaymentStatus::Paid) {
                return ['payment' => $payment, 'newlyPaid' => false];
            }

            $payment->fill([
                'status' => $result->status,
                'gateway_transaction_id' => $result->gatewayTransactionId ?: $payment->gateway_transaction_id,
                // Only ever the VERIFIED response, never the raw inbound POST.
                'payload' => $result->raw ?: $payment->payload,
                'paid_at' => $result->isPaid() ? now() : $payment->paid_at,
                'failed_at' => $result->status === PaymentStatus::Failed ? now() : $payment->failed_at,
            ])->save();

            $appointment = $payment->appointment;

            $appointment->forceFill([
                'payment_status' => $result->status,
                // Whatever happened, the patient is back from the gateway, so
                // the seat should stop being provisional. A failed payment
                // leaves the booking standing and payable at the chamber.
                'hold_expires_at' => null,
            ])->save();

            if (! $result->isPaid()) {
                return ['payment' => $payment, 'newlyPaid' => false];
            }

            /*
             | Paid means confirmed, whatever config('booking.default_status')
             | says. The patient has handed over money; the slot is theirs.
             |
             | Guarded because a chamber may have confirmed it by hand in the
             | meantime, and Confirmed → Confirmed is not a legal move.
             */
            if ($appointment->status->canTransitionTo(AppointmentStatus::Confirmed)) {
                $this->workflow->confirm($appointment->fresh(), BookingActor::System);
            }

            return ['payment' => $payment, 'newlyPaid' => true];
        });

        /*
         | The receipt goes out after the transaction commits, so an SMTP
         | timeout cannot hold a row lock or roll back a recorded payment.
         */
        if ($outcome['newlyPaid']) {
            $this->notifier->paymentReceived($outcome['payment']->fresh());
        }

        return $outcome['payment'];
    }
}
