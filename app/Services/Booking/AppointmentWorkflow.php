<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use App\Exceptions\InvalidStatusTransition;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Services\Notifications\AppointmentNotifier;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Every change to an appointment's status happens here.
 *
 * ===========================================================================
 * WHY THIS IS A SERVICE AND NOT A MODEL OBSERVER
 * ===========================================================================
 *
 * This is the decision a maintainer will be most tempted to "improve", so:
 *
 *   - An observer fires for factories, seeders and bulk edits. `db:seed` would
 *     email the doctor forty times.
 *   - An observer cannot know WHO acted, so the patient can never be told
 *     "you cancelled this" versus "the chamber cancelled this" — and those are
 *     very different things to receive.
 *   - Refusing an illegal transition from inside save() means throwing during
 *     a write, which surfaces as a 500 rather than as a message on a form.
 *
 * The dividing line used throughout this codebase: **pure normalisation
 * belongs in a model hook; anything with a side effect belongs in an explicit
 * service call.** Appointment::booted() derives slot_guard, which is
 * normalisation. Sending email is a side effect, so it lives here.
 *
 * ===========================================================================
 *
 * Callers — all of them one line:
 *
 *   Admin panel     app/Filament/Resources/Appointments/Actions/AppointmentStatusActions.php
 *   Patient portal  app/Http/Controllers/Patient/AppointmentController.php
 *   Payment         app/Services/Payments/PaymentProcessor.php
 *
 * Never call `$appointment->update(['status' => …])` anywhere else.
 */
final class AppointmentWorkflow
{
    public function __construct(
        private readonly AppointmentNotifier $notifier,
        private readonly BookingService $booking,
    ) {}

    /**
     * Move an appointment to a new status.
     *
     * The only method in the application that writes the status column.
     *
     * @throws InvalidStatusTransition
     */
    public function transition(
        Appointment $appointment,
        AppointmentStatus $to,
        BookingActor $actor = BookingActor::System,
        ?string $reason = null,
    ): Appointment {
        $from = $appointment->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransition::between($from, $to);
        }

        $this->guardActor($appointment, $to, $actor);

        DB::transaction(function () use ($appointment, $from, $to, $actor, $reason): void {
            $appointment->markStatus($to);
            $this->stampTimestamp($appointment, $to, $reason);

            // A confirmed or cancelled booking is no longer waiting on a
            // payment window, so the hold must not linger and later "expire".
            if ($to !== AppointmentStatus::Pending) {
                $appointment->hold_expires_at = null;
            }

            $appointment->save();

            $appointment->statusLogs()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor' => $actor,
                'user_id' => $actor === BookingActor::Admin ? Auth::id() : null,
                'reason' => $reason,
                'created_at' => now(),
            ]);
        });

        // Outside the transaction — see the note in BookingService::book().
        $this->notifier->statusChanged($appointment, $from, $actor);

        return $appointment;
    }

    public function confirm(Appointment $appointment, BookingActor $actor = BookingActor::Admin): Appointment
    {
        return $this->transition($appointment, AppointmentStatus::Confirmed, $actor);
    }

    public function complete(Appointment $appointment, BookingActor $actor = BookingActor::Admin): Appointment
    {
        return $this->transition($appointment, AppointmentStatus::Completed, $actor);
    }

    public function cancel(
        Appointment $appointment,
        BookingActor $actor = BookingActor::Admin,
        ?string $reason = null,
    ): Appointment {
        return $this->transition($appointment, AppointmentStatus::Cancelled, $actor, $reason);
    }

    /**
     * Move an appointment to a different time.
     *
     * The old row is NOT edited. A new appointment is booked at the new time
     * and the old one is marked Rescheduled, pointing at its replacement.
     * Editing starts_at in place would throw away what the patient was
     * originally told, and would leave the seat index describing a booking
     * that no longer exists at that time.
     *
     * The new booking goes through BookingService, so it gets the same
     * availability checks and the same concurrency guarantees as any other —
     * a receptionist cannot reschedule someone into a slot that is full.
     *
     * @throws InvalidStatusTransition
     * @throws SlotUnavailableException
     */
    public function reschedule(
        Appointment $appointment,
        CarbonImmutable $newStartsAt,
        BookingActor $actor = BookingActor::Admin,
        ?string $reason = null,
    ): Appointment {
        if (! $appointment->status->canTransitionTo(AppointmentStatus::Rescheduled)) {
            throw InvalidStatusTransition::between($appointment->status, AppointmentStatus::Rescheduled);
        }

        $replacement = $this->booking->bookQuietly(new BookingData(
            patient: $appointment->patient,
            startsAt: $newStartsAt,
            notes: $appointment->notes,
        ));

        DB::transaction(function () use ($appointment, $replacement, $actor, $reason): void {
            $from = $appointment->status;

            $appointment->markStatus(AppointmentStatus::Rescheduled);
            $appointment->rescheduled_to_id = $replacement->getKey();
            $appointment->hold_expires_at = null;
            $appointment->save();

            $appointment->statusLogs()->create([
                'from_status' => $from,
                'to_status' => AppointmentStatus::Rescheduled,
                'actor' => $actor,
                'user_id' => $actor === BookingActor::Admin ? Auth::id() : null,
                'reason' => $reason ?? 'Moved to '.$replacement->dateLabel().', '.$replacement->timeLabel(),
                'created_at' => now(),
            ]);
        });

        /*
         | One email, not two.
         |
         | Booking the replacement quietly and announcing the move here means
         | the patient gets "your appointment has moved to Sunday at 7pm"
         | rather than a cancellation followed by a confirmation, which reads
         | like a mistake and invites a phone call.
         */
        $this->notifier->rescheduled($appointment, $replacement, $actor);

        return $replacement;
    }

    /**
     * Whether this actor may cancel this appointment right now.
     *
     * Used to decide whether to render a cancel button at all — asking first is
     * kinder than letting someone press it and be refused.
     */
    public function canCancel(Appointment $appointment, BookingActor $actor): bool
    {
        if (! $appointment->status->canTransitionTo(AppointmentStatus::Cancelled)) {
            return false;
        }

        return $actor !== BookingActor::Patient || $appointment->isCancellableByPatient();
    }

    /**
     * Rules about who may do what.
     *
     * Staff may do anything the status machine allows. Patients may only
     * cancel, and only with enough notice — a late no-show the chamber knew
     * about is a slot they could have offered somebody else.
     *
     * @throws InvalidStatusTransition
     */
    private function guardActor(Appointment $appointment, AppointmentStatus $to, BookingActor $actor): void
    {
        if ($actor !== BookingActor::Patient) {
            return;
        }

        if ($to !== AppointmentStatus::Cancelled) {
            throw InvalidStatusTransition::forActor($actor, $to);
        }

        if (! $appointment->isCancellableByPatient()) {
            throw InvalidStatusTransition::tooLateToCancel(
                (int) config('booking.cancellation_cutoff_hours', 12),
            );
        }
    }

    /** Record when the change happened, in the column that matches it. */
    private function stampTimestamp(Appointment $appointment, AppointmentStatus $to, ?string $reason): void
    {
        match ($to) {
            AppointmentStatus::Confirmed => $appointment->confirmed_at = now(),
            AppointmentStatus::Completed => $appointment->completed_at = now(),
            AppointmentStatus::Cancelled => tap($appointment, function (Appointment $a) use ($reason): void {
                $a->cancelled_at = now();
                $a->cancellation_reason = $reason;
            }),
            default => null,
        };
    }

    /**
     * The soonest a patient may still cancel, for display.
     *
     * "You can cancel online until 6am on Sunday" is far more useful than
     * "cancellations close 12 hours before".
     */
    public function cancellationDeadline(Appointment $appointment): CarbonImmutable
    {
        return $appointment->startsAtLocal()
            ->subHours((int) config('booking.cancellation_cutoff_hours', 12))
            ->setTimezone(Clock::timezone());
    }
}
