<?php

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Livewire\BookingWizard;
use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Notifications\AppointmentBookedDoctor;
use App\Notifications\AppointmentBookedPatient;
use App\Support\Clock;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    freezeClinicClock('2026-09-01 09:00:00');
    Cache::flush();
    Notification::fake();

    DoctorProfile::create([
        'name' => 'Dr. Test',
        'specialization' => 'Cardiology',
        'email' => 'chamber@example.com',
        'consultation_fee' => 1500,
    ]);
    DoctorProfile::forgetCurrent();

    AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '19:30')->duration(30)->create();

    $this->sunday = Clock::today()->addDay();
    while ($this->sunday->dayOfWeek !== 0) {
        $this->sunday = $this->sunday->addDay();
    }

    $this->slotKey = $this->sunday->setTime(18, 0)->format('Y-m-d H:i');
    $this->patient = Patient::factory()->create();
});

it('renders the booking page', function () {
    $this->get(route('booking'))
        ->assertOk()
        ->assertSeeLivewire(BookingWizard::class);
});

it('walks a signed-in patient from date to confirmed booking', function () {
    // No gateway credentials in the test env, so "pay at the chamber" is the
    // only option and the wizard books immediately.
    Livewire::actingAs($this->patient, 'patient')
        ->test(BookingWizard::class)
        ->call('selectDate', $this->sunday->toDateString())
        ->assertSet('step', BookingWizard::STEP_TIME)
        ->call('selectSlot', $this->slotKey)
        ->assertSet('step', BookingWizard::STEP_DETAILS)
        ->set('notes', 'Short of breath on stairs.')
        ->call('continueToPayment')
        ->assertSet('bookedReference', fn ($ref) => filled($ref));

    $appointment = Appointment::query()->first();

    expect($appointment->patient_id)->toBe($this->patient->id)
        ->and($appointment->notes)->toBe('Short of breath on stairs.')
        ->and($appointment->startsAtLocal()->format('g:i A'))->toBe('6:00 PM')
        ->and($appointment->payment_status)->toBe(PaymentStatus::DueAtClinic);
});

it('stops a guest at the details step and parks their slot', function () {
    Livewire::test(BookingWizard::class)
        ->call('selectDate', $this->sunday->toDateString())
        ->call('selectSlot', $this->slotKey)
        ->assertSet('step', BookingWizard::STEP_DETAILS)
        // The prompt to sign in, not a confirm button.
        ->assertSee('Sign in to confirm')
        ->call('continueToPayment')
        // Still nothing booked.
        ->assertSet('bookedReference', null);

    expect(Appointment::query()->count())->toBe(0)
        // Parked, so they come back to the same time after registering.
        ->and(session('booking.slot'))->toBe($this->slotKey);
});

it('picks the parked slot back up after the patient signs in', function () {
    session(['booking.slot' => $this->slotKey]);

    Livewire::actingAs($this->patient, 'patient')
        ->test(BookingWizard::class)
        ->assertSet('selectedSlot', $this->slotKey)
        ->assertSet('step', BookingWizard::STEP_DETAILS);
});

it('explains itself when the parked slot was taken while they registered', function () {
    session(['booking.slot' => $this->slotKey]);

    // Somebody else booked it in the meantime.
    Appointment::factory()->at($this->sunday->setTime(18, 0))->create();

    Livewire::actingAs($this->patient, 'patient')
        ->test(BookingWizard::class)
        ->assertSet('step', BookingWizard::STEP_TIME)
        ->assertSet('errorMessage', fn ($m) => str_contains((string) $m, 'taken while you were signing in'));

    expect(session('booking.slot'))->toBeNull();
});

it('refuses a slot that filled up between rendering and clicking', function () {
    Appointment::factory()->at($this->sunday->setTime(18, 0))->create();

    Livewire::actingAs($this->patient, 'patient')
        ->test(BookingWizard::class)
        ->call('selectDate', $this->sunday->toDateString())
        ->call('selectSlot', $this->slotKey)
        // Held at the time step with an explanation, not advanced.
        ->assertSet('step', BookingWizard::STEP_TIME)
        ->assertSet('errorMessage', fn ($m) => str_contains((string) $m, 'just been taken'));
});

it('will not book without a slot', function () {
    Livewire::actingAs($this->patient, 'patient')
        ->test(BookingWizard::class)
        ->call('confirm')
        ->assertSet('bookedReference', null)
        ->assertSet('step', BookingWizard::STEP_DATE);
});

it('sends both emails once the booking is made', function () {
    Livewire::actingAs($this->patient, 'patient')
        ->test(BookingWizard::class)
        ->call('selectDate', $this->sunday->toDateString())
        ->call('selectSlot', $this->slotKey)
        ->call('continueToPayment');

    Notification::assertSentOnDemand(AppointmentBookedPatient::class);
    Notification::assertSentOnDemand(AppointmentBookedDoctor::class);
});

describe('when an online gateway is configured', function () {
    beforeEach(function () {
        config()->set('booking.payment.gateways.sslcommerz.store_id', 'testbox');
        config()->set('booking.payment.gateways.sslcommerz.store_password', 'testpass');
    });

    it('offers the payment step and holds the seat while paying', function () {
        Livewire::actingAs($this->patient, 'patient')
            ->test(BookingWizard::class)
            ->call('selectDate', $this->sunday->toDateString())
            ->call('selectSlot', $this->slotKey)
            ->call('continueToPayment')
            ->assertSet('step', BookingWizard::STEP_PAYMENT)
            ->set('gateway', 'sslcommerz')
            ->call('confirm')
            ->assertSet('handingOffToGateway', true);

        $appointment = Appointment::query()->first();

        // The seat is held only until the payment window lapses, so an
        // abandoned checkout releases it.
        expect($appointment->payment_status)->toBe(PaymentStatus::Pending)
            ->and($appointment->hold_expires_at)->not->toBeNull()
            ->and($appointment->status)->toBe(AppointmentStatus::Pending);
    });

    it('does not hold the seat when paying at the chamber', function () {
        Livewire::actingAs($this->patient, 'patient')
            ->test(BookingWizard::class)
            ->call('selectDate', $this->sunday->toDateString())
            ->call('selectSlot', $this->slotKey)
            ->call('continueToPayment')
            ->set('gateway', 'cash')
            ->call('confirm');

        expect(Appointment::query()->first()->hold_expires_at)->toBeNull();
    });
});
