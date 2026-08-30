<?php

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Notifications\AppointmentBookedDoctor;
use App\Notifications\AppointmentBookedPatient;
use App\Notifications\AppointmentRescheduledPatient;
use App\Notifications\AppointmentStatusChangedDoctor;
use App\Notifications\AppointmentStatusChangedPatient;
use App\Services\Booking\AppointmentWorkflow;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingService;
use App\Support\Clock;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    freezeClinicClock('2026-09-01 09:00:00');
    Cache::flush();
    Notification::fake();

    DoctorProfile::create([
        'name' => 'Dr. Test',
        'specialization' => 'Cardiology',
        'email' => 'chamber@example.com',
        'phone' => '+8801700000000',
        'consultation_fee' => 1500,
    ]);
    DoctorProfile::forgetCurrent();

    $this->patient = Patient::factory()->create();
    $this->booking = app(BookingService::class);
    $this->workflow = app(AppointmentWorkflow::class);

    // Every Sunday evening, three half-hour slots, two patients each.
    AvailabilitySlot::factory()->weeklyOn(0)->between('18:00', '19:30')->duration(30)->capacity(2)->create();

    $this->sunday = Clock::today()->addDay();
    while ($this->sunday->dayOfWeek !== 0) {
        $this->sunday = $this->sunday->addDay();
    }
});

/** Shorthand for booking the 6pm slot on the seeded Sunday. */
function bookAtSix(Patient $patient, ?string $notes = null): Appointment
{
    return app(BookingService::class)->book(new BookingData(
        patient: $patient,
        startsAt: test()->sunday->setTime(18, 0),
        notes: $notes,
    ));
}

describe('booking a slot', function () {
    it('creates an appointment and snapshots the details that must not change', function () {
        $appointment = bookAtSix($this->patient, 'I get short of breath on stairs.');

        expect($appointment->patient_id)->toBe($this->patient->id)
            // Contact details are copied, not merely related — the record must
            // stay true even if the patient edits their profile next year.
            ->and($appointment->patient_name)->toBe($this->patient->name)
            ->and($appointment->patient_phone)->toBe($this->patient->phone)
            ->and($appointment->notes)->toBe('I get short of breath on stairs.')
            // The fee is snapshotted from the profile, never posted by the form.
            ->and((float) $appointment->fee_amount)->toBe(1500.0)
            ->and($appointment->currency)->toBe('BDT')
            ->and($appointment->status)->toBe(AppointmentStatus::Pending)
            ->and($appointment->seat_no)->toBe(1);
    });

    it('derives the end time from the rule rather than trusting the caller', function () {
        $appointment = bookAtSix($this->patient);

        expect($appointment->startsAtLocal()->format('g:i A'))->toBe('6:00 PM')
            ->and($appointment->endsAtLocal()->format('g:i A'))->toBe('6:30 PM');
    });

    it('honours the configured default status', function () {
        config()->set('booking.default_status', 'confirmed');

        expect(bookAtSix($this->patient)->status)->toBe(AppointmentStatus::Confirmed);
    });

    it('records who created the appointment', function () {
        $appointment = bookAtSix($this->patient);

        $log = $appointment->statusLogs()->first();

        expect($log->from_status)->toBeNull()
            ->and($log->to_status)->toBe(AppointmentStatus::Pending)
            ->and($log->actor)->toBe(BookingActor::Patient);
    });

    it('tells both the patient and the chamber', function () {
        bookAtSix($this->patient);

        Notification::assertSentOnDemand(AppointmentBookedPatient::class);
        Notification::assertSentOnDemand(AppointmentBookedDoctor::class);
    });

    it('holds the seat only temporarily when payment is pending', function () {
        $appointment = $this->booking->book(
            new BookingData($this->patient, $this->sunday->setTime(18, 0)),
            holdForPayment: true,
        );

        expect($appointment->payment_status)->toBe(PaymentStatus::Pending)
            ->and($appointment->hold_expires_at)->not->toBeNull();
    });

    it('marks a booking as due at the chamber when no payment is taken', function () {
        $appointment = bookAtSix($this->patient);

        // No gateway involved, so there is nothing to expire and the seat is
        // held outright rather than provisionally.
        expect($appointment->payment_status)->toBe(PaymentStatus::DueAtClinic)
            ->and($appointment->hold_expires_at)->toBeNull();
    });
});

describe('refusing a booking', function () {
    it('refuses a time no rule ever offered', function () {
        // 6:15 is not on the half hour: a hand-edited form submission.
        expect(fn () => $this->booking->book(
            new BookingData($this->patient, $this->sunday->setTime(18, 15)),
        ))->toThrow(SlotUnavailableException::class);
    });

    it('refuses a time in the past', function () {
        expect(fn () => $this->booking->book(
            new BookingData($this->patient, Clock::today()->subWeek()->setTime(18, 0)),
        ))->toThrow(SlotUnavailableException::class);
    });

    it('refuses once every seat is taken', function () {
        bookAtSix($this->patient);
        bookAtSix(Patient::factory()->create());

        // Capacity is two.
        expect(fn () => bookAtSix(Patient::factory()->create()))
            ->toThrow(SlotUnavailableException::class);
    });

    it('refuses the same patient booking the same time twice', function () {
        bookAtSix($this->patient);

        expect(fn () => bookAtSix($this->patient))
            ->toThrow(SlotUnavailableException::class, 'You already have an appointment at this time.');
    });

    it('sends nothing when the booking was refused', function () {
        try {
            $this->booking->book(new BookingData($this->patient, $this->sunday->setTime(18, 15)));
        } catch (SlotUnavailableException) {
            // expected
        }

        Notification::assertNothingSent();
    });
});

describe('seat allocation', function () {
    it('gives each patient their own seat in a shared slot', function () {
        $first = bookAtSix($this->patient);
        $second = bookAtSix(Patient::factory()->create());

        expect($first->seat_no)->toBe(1)
            ->and($second->seat_no)->toBe(2);
    });

    it('reuses the lowest free seat after a cancellation', function () {
        $first = bookAtSix($this->patient);
        bookAtSix(Patient::factory()->create());

        $this->workflow->cancel($first, BookingActor::Admin);

        // Seat 1 is free again, so the next patient gets it rather than a third
        // number — which matters in a chamber that calls patients by number.
        expect(bookAtSix(Patient::factory()->create())->seat_no)->toBe(1);
    });

    it('reclaims a seat whose payment window has lapsed', function () {
        $abandoned = $this->booking->book(
            new BookingData($this->patient, $this->sunday->setTime(18, 0)),
            holdForPayment: true,
        );

        // The patient went to the gateway and never came back.
        $abandoned->forceFill(['hold_expires_at' => now()->subMinute()])->save();

        bookAtSix(Patient::factory()->create());

        expect($abandoned->fresh()->status)->toBe(AppointmentStatus::Cancelled)
            ->and($abandoned->fresh()->cancellation_reason)->toContain('not completed in time');
    });
});

describe('the status workflow', function () {
    it('confirms a pending appointment and stamps the time', function () {
        $appointment = $this->workflow->confirm(bookAtSix($this->patient));

        expect($appointment->status)->toBe(AppointmentStatus::Confirmed)
            ->and($appointment->confirmed_at)->not->toBeNull();
    });

    it('refuses an illegal move', function () {
        $appointment = bookAtSix($this->patient);
        $this->workflow->cancel($appointment, BookingActor::Admin);

        // Cancelled is terminal.
        expect(fn () => $this->workflow->confirm($appointment))
            ->toThrow(InvalidStatusTransition::class);
    });

    it('writes an audit entry naming the actor', function () {
        $appointment = bookAtSix($this->patient);
        $this->workflow->cancel($appointment, BookingActor::Patient, 'Something came up');

        $log = $appointment->statusLogs()->first();

        expect($log->from_status)->toBe(AppointmentStatus::Pending)
            ->and($log->to_status)->toBe(AppointmentStatus::Cancelled)
            ->and($log->actor)->toBe(BookingActor::Patient)
            ->and($log->reason)->toBe('Something came up');
    });

    it('does not email the doctor about their own action', function () {
        $this->workflow->confirm(bookAtSix($this->patient), BookingActor::Admin);

        Notification::assertSentOnDemand(AppointmentStatusChangedPatient::class);
        Notification::assertNotSentTo(
            Notification::route('mail', 'chamber@example.com'),
            AppointmentStatusChangedDoctor::class,
        );
    });

    it('does tell the doctor when the patient cancels', function () {
        $this->workflow->cancel(bookAtSix($this->patient), BookingActor::Patient);

        Notification::assertSentOnDemand(AppointmentStatusChangedDoctor::class);
    });

    it('stops a patient cancelling too close to the appointment', function () {
        config()->set('booking.cancellation_cutoff_hours', 12);

        // Book something later today, inside the cutoff.
        AvailabilitySlot::factory()
            ->weeklyOn(Clock::today()->dayOfWeek)
            ->between('15:00', '15:30')
            ->duration(30)
            ->create();
        Cache::flush();

        $appointment = $this->booking->book(
            new BookingData($this->patient, Clock::today()->setTime(15, 0)),
        );

        expect(fn () => $this->workflow->cancel($appointment, BookingActor::Patient))
            ->toThrow(InvalidStatusTransition::class, 'telephone the chamber');

        // The chamber itself is not restricted.
        expect($this->workflow->cancel($appointment, BookingActor::Admin)->status)
            ->toBe(AppointmentStatus::Cancelled);
    });

    it('refuses to let a patient confirm their own appointment', function () {
        $appointment = bookAtSix($this->patient);

        expect(fn () => $this->workflow->transition($appointment, AppointmentStatus::Confirmed, BookingActor::Patient))
            ->toThrow(InvalidStatusTransition::class);
    });
});

describe('rescheduling', function () {
    it('creates a new appointment and closes the old one', function () {
        $original = bookAtSix($this->patient);

        $replacement = $this->workflow->reschedule($original, $this->sunday->setTime(19, 0));

        expect($original->fresh()->status)->toBe(AppointmentStatus::Rescheduled)
            ->and($original->fresh()->rescheduled_to_id)->toBe($replacement->id)
            ->and($replacement->startsAtLocal()->format('g:i A'))->toBe('7:00 PM')
            ->and($replacement->status)->toBe(AppointmentStatus::Pending);
    });

    it('frees the original seat for someone else', function () {
        $original = bookAtSix($this->patient);
        bookAtSix(Patient::factory()->create());

        // Both seats at 6pm are gone.
        $this->workflow->reschedule($original, $this->sunday->setTime(19, 0));

        // Now one is free again.
        expect(bookAtSix(Patient::factory()->create()))->toBeInstanceOf(Appointment::class);
    });

    it('sends one "moved" message rather than a cancellation and a booking', function () {
        Notification::fake();

        $this->workflow->reschedule(bookAtSix($this->patient), $this->sunday->setTime(19, 0));

        Notification::assertSentOnDemand(AppointmentRescheduledPatient::class);
        Notification::assertNotSentTo(
            Notification::route('mail', $this->patient->email),
            AppointmentStatusChangedPatient::class,
        );
    });

    it('will not move an appointment into a slot that is full', function () {
        $original = bookAtSix($this->patient);

        // Fill 7pm completely.
        $seven = $this->sunday->setTime(19, 0);
        $this->booking->book(new BookingData(Patient::factory()->create(), $seven));
        $this->booking->book(new BookingData(Patient::factory()->create(), $seven));

        expect(fn () => $this->workflow->reschedule($original, $seven))
            ->toThrow(SlotUnavailableException::class);

        // The original survives the failed move.
        expect($original->fresh()->status)->toBe(AppointmentStatus::Pending);
    });
});
