<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentInitiationFailed;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Starting a payment.
 *
 * One code path for every method, including "pay at the chamber" — that is
 * what making cash a gateway buys. Nothing here knows which processor is in
 * use.
 */
class PaymentController extends Controller
{
    public function start(
        Request $request,
        Appointment $appointment,
        PaymentManager $gateways,
    ): RedirectResponse|View {
        $this->authorize('view', $appointment);

        $validated = $request->validate([
            'gateway' => ['nullable', 'string', 'max:30'],
        ]);

        // Nothing to pay for.
        if (! $appointment->fee_amount || (float) $appointment->fee_amount <= 0) {
            return redirect()
                ->route('patient.appointments.show', $appointment)
                ->with('status', 'There is no fee to pay for this appointment.');
        }

        // Already settled — pressing back and re-submitting must not take the
        // money twice.
        if ($appointment->payment_status === PaymentStatus::Paid) {
            return redirect()
                ->route('patient.appointments.show', $appointment)
                ->with('status', 'This appointment has already been paid for.');
        }

        $name = $validated['gateway'] ?? config('booking.payment.default');

        if (! $gateways->has($name)) {
            return redirect()
                ->route('patient.appointments.show', $appointment)
                ->withErrors(['payment' => 'That payment method is not available at the moment.']);
        }

        $gateway = $gateways->driver($name);

        $payment = Payment::create([
            'appointment_id' => $appointment->getKey(),
            'gateway' => $gateway->name(),
            'amount' => $appointment->fee_amount,
            'currency' => $appointment->currency ?: config('booking.payment.currency', 'BDT'),
            'status' => PaymentStatus::Pending,
        ]);

        try {
            $redirect = $gateway->initiate($payment);
        } catch (PaymentInitiationFailed $e) {
            report($e);

            $payment->forceFill([
                'status' => PaymentStatus::Failed,
                'failed_at' => now(),
            ])->save();

            /*
             | The booking is NOT cancelled. A gateway being down is our problem,
             | not the patient's, and they still have an appointment they can
             | pay for at the chamber.
             */
            $appointment->forceFill([
                'payment_status' => PaymentStatus::DueAtClinic,
                'hold_expires_at' => null,
            ])->save();

            return redirect()
                ->route('patient.appointments.show', $appointment)
                ->withErrors(['payment' => $e->patientMessage()]);
        }

        /*
         | Some gateways need a form POST rather than a plain redirect, so the
         | view renders an auto-submitting form. Handled here rather than in
         | each driver so a new gateway gets it for free.
         */
        if ($redirect->isPost()) {
            return view('pages.payments.handoff', [
                'redirect' => $redirect,
                'gatewayLabel' => $gateway->label(),
            ]);
        }

        return redirect()->away($redirect->url);
    }
}
