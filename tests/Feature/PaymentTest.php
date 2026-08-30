<?php

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\Payment;
use App\Notifications\PaymentReceiptPatient;
use App\Services\Payments\PaymentManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
|
| The security-critical part of this file is "verification". A public callback
| URL takes an attacker-controlled POST, so the ONLY thing making a payment
| real is what the gateway's own API says — including the amount.
|
*/

beforeEach(function () {
    freezeClinicClock();
    Notification::fake();

    DoctorProfile::create([
        'name' => 'Dr. Test',
        'specialization' => 'Cardiology',
        'email' => 'chamber@example.com',
        'consultation_fee' => 1500,
    ]);
    DoctorProfile::forgetCurrent();

    config()->set('booking.payment.gateways.sslcommerz.store_id', 'testbox');
    config()->set('booking.payment.gateways.sslcommerz.store_password', 'testpass');
    config()->set('booking.payment.gateways.sslcommerz.sandbox', true);

    $this->appointment = Appointment::factory()->create([
        'fee_amount' => 1500,
        'currency' => 'BDT',
        'payment_status' => PaymentStatus::Pending,
        'hold_expires_at' => now()->addMinutes(15),
    ]);
});

/** Build a pending payment ready for a callback. */
function pendingPayment(Appointment $appointment): Payment
{
    return Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'gateway' => 'sslcommerz',
        'amount' => 1500,
        'currency' => 'BDT',
        'status' => PaymentStatus::Pending,
    ]);
}

/** What SSLCommerz's validation API returns for a genuine payment. */
function validationResponse(array $overrides = []): array
{
    return array_merge([
        'status' => 'VALID',
        'tran_id' => 'REPLACE-ME',
        'amount' => '1500.00',
        'currency' => 'BDT',
        'bank_tran_id' => 'BANK123456',
    ], $overrides);
}

describe('the gateway manager', function () {
    it('offers pay-at-clinic even with no credentials anywhere', function () {
        config()->set('booking.payment.gateways.sslcommerz.store_id', null);
        config()->set('booking.payment.gateways.sslcommerz.store_password', null);

        $available = app(PaymentManager::class)->available();

        // A fresh install with an empty .env still takes bookings.
        expect($available)->toHaveCount(1)
            ->and($available->first()->name())->toBe(PaymentManager::PAY_AT_CLINIC);
    });

    it('hides a gateway whose credentials are missing rather than failing', function () {
        config()->set('booking.payment.gateways.sslcommerz.store_id', null);

        expect(app(PaymentManager::class)->has('sslcommerz'))->toBeFalse();
    });

    it('offers both once credentials are present', function () {
        expect(app(PaymentManager::class)->available())->toHaveCount(2);
    });

    it('refuses to resolve a gateway that is not configured', function () {
        expect(fn () => app(PaymentManager::class)->driver('nonesuch'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('starting a payment', function () {
    it('sends the patient to the gateway page', function () {
        Http::fake([
            '*gwprocess*' => Http::response([
                'status' => 'SUCCESS',
                'sessionkey' => 'SESSION123',
                'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/abc123',
            ]),
        ]);

        $this->actingAs($this->appointment->patient, 'patient')
            ->post(route('payments.start', $this->appointment), ['gateway' => 'sslcommerz'])
            ->assertRedirect('https://sandbox.sslcommerz.com/pay/abc123');

        expect(Payment::query()->first()->gateway_session_key)->toBe('SESSION123');
    });

    it('keeps the booking when the gateway will not start a session', function () {
        Http::fake(['*gwprocess*' => Http::response(['status' => 'FAILED', 'failedreason' => 'Store not live'])]);

        $this->actingAs($this->appointment->patient, 'patient')
            ->post(route('payments.start', $this->appointment), ['gateway' => 'sslcommerz'])
            ->assertRedirect(route('patient.appointments.show', $this->appointment))
            ->assertSessionHasErrors('payment');

        // A gateway being down is our problem, not the patient's — they keep
        // the appointment and can pay at the chamber.
        expect($this->appointment->fresh()->payment_status)->toBe(PaymentStatus::DueAtClinic)
            ->and($this->appointment->fresh()->status)->toBe(AppointmentStatus::Pending);
    });

    it('will not charge twice for an appointment already paid', function () {
        $this->appointment->forceFill(['payment_status' => PaymentStatus::Paid])->save();

        $this->actingAs($this->appointment->patient, 'patient')
            ->post(route('payments.start', $this->appointment), ['gateway' => 'sslcommerz'])
            ->assertRedirect(route('patient.appointments.show', $this->appointment));

        expect(Payment::query()->count())->toBe(0);
    });

    it('refuses to let one patient pay for another\'s appointment', function () {
        $stranger = Patient::factory()->create();

        $this->actingAs($stranger, 'patient')
            ->post(route('payments.start', $this->appointment), ['gateway' => 'sslcommerz'])
            ->assertForbidden();
    });
});

describe('verifying a callback', function () {
    it('confirms the appointment when the gateway says the payment is genuine', function () {
        $payment = pendingPayment($this->appointment);

        Http::fake([
            '*validationserverAPI*' => Http::response(validationResponse(['tran_id' => $payment->reference])),
        ]);

        $this->post(route('payments.callback', ['sslcommerz', 'success']), [
            'tran_id' => $payment->reference,
            'val_id' => 'VAL123',
        ])->assertRedirect(route('patient.appointments.show', $this->appointment));

        expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
            ->and($payment->fresh()->gateway_transaction_id)->toBe('BANK123456')
            // Paid means confirmed, whatever the default booking status says.
            ->and($this->appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed)
            ->and($this->appointment->fresh()->payment_status)->toBe(PaymentStatus::Paid)
            // No longer provisional.
            ->and($this->appointment->fresh()->hold_expires_at)->toBeNull();

        Notification::assertSentOnDemand(PaymentReceiptPatient::class);
    });

    it('REFUSES a payment reporting the wrong amount', function () {
        /*
         | The single most important test in this file.
         |
         | Without the amount check, a patient re-posts the success callback
         | having paid one taka and walks away with a confirmed appointment.
         */
        $payment = pendingPayment($this->appointment);

        Http::fake([
            '*validationserverAPI*' => Http::response(validationResponse([
                'tran_id' => $payment->reference,
                'amount' => '1.00',
            ])),
        ]);

        $this->post(route('payments.callback', ['sslcommerz', 'success']), [
            'tran_id' => $payment->reference,
            'val_id' => 'VAL123',
        ]);

        expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
            ->and($this->appointment->fresh()->status)->not->toBe(AppointmentStatus::Confirmed);

        Notification::assertNothingSent();
    });

    it('refuses a payment reporting the wrong currency', function () {
        $payment = pendingPayment($this->appointment);

        Http::fake([
            '*validationserverAPI*' => Http::response(validationResponse([
                'tran_id' => $payment->reference,
                'currency' => 'USD',
            ])),
        ]);

        $this->post(route('payments.callback', ['sslcommerz', 'success']), [
            'tran_id' => $payment->reference,
            'val_id' => 'VAL123',
        ]);

        expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
    });

    it('refuses when the gateway reports a different transaction id', function () {
        $payment = pendingPayment($this->appointment);

        Http::fake([
            '*validationserverAPI*' => Http::response(validationResponse(['tran_id' => 'SOMEONE-ELSES'])),
        ]);

        $this->post(route('payments.callback', ['sslcommerz', 'success']), [
            'tran_id' => $payment->reference,
            'val_id' => 'VAL123',
        ]);

        expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
    });

    it('refuses a forged callback that never went near the gateway', function () {
        $payment = pendingPayment($this->appointment);

        // Whatever this POST claims, the validation API is the authority.
        Http::fake([
            '*validationserverAPI*' => Http::response(['status' => 'INVALID_TRANSACTION']),
        ]);

        $this->post(route('payments.callback', ['sslcommerz', 'success']), [
            'tran_id' => $payment->reference,
            'val_id' => 'FORGED',
            'status' => 'VALID',
            'amount' => '1500.00',
        ]);

        expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
            ->and($this->appointment->fresh()->status)->not->toBe(AppointmentStatus::Confirmed);
    });

    it('treats a cancelled payment as a decision, not a failure', function () {
        $payment = pendingPayment($this->appointment);

        $this->post(route('payments.callback', ['sslcommerz', 'cancel']), [
            'tran_id' => $payment->reference,
            'status' => 'CANCELLED',
        ])->assertRedirect(route('patient.appointments.show', $this->appointment));

        expect($payment->fresh()->status)->toBe(PaymentStatus::Cancelled)
            // The appointment survives — they can still pay at the chamber.
            ->and($this->appointment->fresh()->status)->toBe(AppointmentStatus::Pending)
            ->and($this->appointment->fresh()->hold_expires_at)->toBeNull();
    });
});

describe('idempotency', function () {
    it('handles the success callback and the IPN without paying twice', function () {
        $payment = pendingPayment($this->appointment);

        Http::fake([
            '*validationserverAPI*' => Http::response(validationResponse(['tran_id' => $payment->reference])),
        ]);

        $body = ['tran_id' => $payment->reference, 'val_id' => 'VAL123'];

        // The browser comes back…
        $this->post(route('payments.callback', ['sslcommerz', 'success']), $body);
        // …and the gateway's own server posts the same news, twice.
        $this->post(route('payments.ipn', 'sslcommerz'), $body)->assertOk();
        $this->post(route('payments.ipn', 'sslcommerz'), $body)->assertOk();

        expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
            ->and($this->appointment->fresh()->status)->toBe(AppointmentStatus::Confirmed);

        // Exactly one receipt, not three.
        Notification::assertSentOnDemandTimes(PaymentReceiptPatient::class, 1);
    });

    it('answers an IPN it cannot match with 200 rather than making it retry', function () {
        $this->post(route('payments.ipn', 'sslcommerz'), ['tran_id' => 'NOT-OURS'])
            ->assertOk()
            ->assertJson(['matched' => false]);
    });
});

describe('pay at the chamber', function () {
    it('records the fee as due without contacting anybody', function () {
        Http::fake();   // Nothing should be called.

        $this->actingAs($this->appointment->patient, 'patient')
            ->post(route('payments.start', $this->appointment), ['gateway' => 'cash'])
            ->assertRedirect(route('patient.appointments.show', $this->appointment));

        expect($this->appointment->fresh()->payment_status)->toBe(PaymentStatus::DueAtClinic)
            ->and($this->appointment->fresh()->hold_expires_at)->toBeNull();

        Http::assertNothingSent();
    });
});
