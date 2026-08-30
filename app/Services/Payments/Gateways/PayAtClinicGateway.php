<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymentManager;
use App\Support\Payments\PaymentRedirect;
use App\Support\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * Paying in person.
 *
 * ===========================================================================
 * WHY "PAY AT THE CHAMBER" IS A GATEWAY AND NOT A SPECIAL CASE
 * ===========================================================================
 *
 * It would be easy to write `if ($method === 'cash') { … }` in the booking
 * flow. Making it a driver instead means:
 *
 *   * the checkout screen is one radio rendered from PaymentManager::available()
 *     rather than a hard-coded option plus a loop;
 *   * PaymentController::start() has exactly ONE code path for every payment
 *     method, including none;
 *   * nothing in the booking layer knows what "cash" means.
 *
 * This class is the proof that the abstraction is real. If the PaymentGateway
 * contract could not express "take no money at all", it would be shaped around
 * SSLCommerz rather than around payments.
 */
final class PayAtClinicGateway implements PaymentGateway
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config = []) {}

    public function name(): string
    {
        return PaymentManager::PAY_AT_CLINIC;
    }

    public function label(): string
    {
        return $this->config['label'] ?? 'Pay at the chamber';
    }

    /** Switched on and off from config/booking.php like any other gateway. */
    public function isConfigured(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * Record that the fee is owed, and send the patient to their confirmation.
     *
     * No hold is set: there is no gateway page to abandon, so the seat is the
     * patient's outright rather than provisionally.
     */
    public function initiate(Payment $payment): PaymentRedirect
    {
        $payment->forceFill([
            'status' => PaymentStatus::DueAtClinic,
        ])->save();

        $payment->appointment->forceFill([
            'payment_status' => PaymentStatus::DueAtClinic,
            'hold_expires_at' => null,
        ])->save();

        return PaymentRedirect::toConfirmation(
            route('patient.appointments.show', $payment->appointment),
        );
    }

    /** Nothing ever calls back, because nothing ever left. */
    public function handleCallback(Request $request): PaymentResult
    {
        return new PaymentResult(
            status: PaymentStatus::DueAtClinic,
            reference: (string) $request->input('tran_id'),
            message: 'This fee is payable at the chamber.',
        );
    }

    public function verify(Payment $payment): PaymentResult
    {
        return new PaymentResult(
            status: $payment->status,
            reference: $payment->reference,
            amount: (string) $payment->amount,
            currency: $payment->currency,
        );
    }
}
