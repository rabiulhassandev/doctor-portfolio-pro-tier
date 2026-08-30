<?php

namespace App\Services\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use App\Enums\PaymentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\AvailabilitySlot;
use App\Services\Notifications\AppointmentNotifier;
use App\Support\Slot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Turns a chosen time into a booked appointment.
 *
 * ===========================================================================
 * HOW TWO PATIENTS ARE STOPPED FROM TAKING THE SAME SEAT
 * ===========================================================================
 *
 * Two mechanisms, doing different jobs:
 *
 *   1. A transaction with a row lock, so the common case produces a clear
 *      message ("someone just booked that") rather than a database error.
 *
 *   2. A unique index on the appointments table, which is the actual
 *      guarantee. It holds even when a booking is made from tinker, a seeder,
 *      or some future admin screen whose author forgot this service exists.
 *
 * Application checks alone cannot do this. Two requests can both read "seat 2
 * is free" before either writes, and no amount of careful PHP closes that
 * window. The index does.
 *
 * ===========================================================================
 * WHAT THIS CLASS MAY NOT KNOW ABOUT
 * ===========================================================================
 *
 * Nothing under App\Services\Payments\Gateways or App\Services\Sms. Booking a
 * seat and taking money are separate concerns, and keeping them separate is
 * what lets a buyer swap payment provider without touching any of this. There
 * is an architecture test enforcing it.
 */
final class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AppointmentNotifier $notifier,
    ) {}

    /**
     * Book an appointment.
     *
     * @param  bool  $holdForPayment  When true the seat is held only until the
     *                                payment window lapses, so an abandoned
     *                                checkout releases it. Set by the payment
     *                                flow, not by the patient.
     *
     * @throws SlotUnavailableException
     */
    public function book(BookingData $data, bool $holdForPayment = false): Appointment
    {
        $appointment = $this->createWithinTransaction($data, $holdForPayment);

        /*
         | Notifications go out AFTER the transaction has committed.
         |
         | Sending inside it would hold row locks open for the length of an SMTP
         | conversation, which on a slow mail host is seconds — and every other
         | patient trying to book that evening would be queued behind it. It
         | also means a mail failure cannot roll back a perfectly good booking.
         */
        $this->notifier->booked($appointment);

        return $appointment;
    }

    /**
     * Book a seat without telling anybody.
     *
     * Used only by AppointmentWorkflow::reschedule(), which sends its own
     * single "your appointment has moved" message afterwards. Without this the
     * patient would receive a fresh booking confirmation immediately followed
     * by a cancellation of the old one — which reads like a mistake and earns
     * the chamber a phone call.
     *
     * All the availability and concurrency guarantees still apply; only the
     * notification is suppressed.
     *
     * @throws SlotUnavailableException
     */
    public function bookQuietly(BookingData $data): Appointment
    {
        return $this->createWithinTransaction($data, holdForPayment: false);
    }

    /**
     * The part that must be atomic.
     *
     * @throws SlotUnavailableException
     */
    private function createWithinTransaction(BookingData $data, bool $holdForPayment): Appointment
    {
        try {
            return DB::transaction(function () use ($data, $holdForPayment): Appointment {
                // 1. Re-derive the slot from the rules. The form posted a time
                //    and nothing else; capacity, duration and fee come from here.
                $slot = $this->availability->findBookableSlot($data->startsAt)
                    ?? throw SlotUnavailableException::forTime($data->startsAt);

                /*
                 | 2. Lock the availability RULE row, not the appointments.
                 |
                 | Locking appointments would be useless when the slot is still
                 | empty: `SELECT … FOR UPDATE` on rows that do not exist locks
                 | nothing, so two racers would both find zero and both insert.
                 | Locking the parent rule serialises everyone booking inside
                 | this block of the evening, which is the granularity we want.
                 */
                AvailabilitySlot::query()
                    ->whereKey($slot->availabilitySlotId)
                    ->lockForUpdate()
                    ->first();

                // 3. Reclaim seats whose payment window has lapsed, so the
                //    database agrees with what the availability page showed.
                $this->releaseExpiredHolds($slot);

                // 4. A patient rushing the button should not end up with two
                //    identical appointments.
                $this->guardAgainstDuplicate($data, $slot);

                $seat = $this->allocateSeat($slot);

                $appointment = Appointment::create([
                    ...$data->toAttributes($slot, $seat),
                    'status' => $this->initialStatus(),
                    'payment_status' => $holdForPayment ? PaymentStatus::Pending : PaymentStatus::DueAtClinic,
                    'hold_expires_at' => $holdForPayment
                        ? now()->addMinutes((int) config('booking.payment.hold_minutes', 15))
                        : null,
                ]);

                $appointment->statusLogs()->create([
                    'from_status' => null,
                    'to_status' => $appointment->status,
                    'actor' => BookingActor::Patient,
                    'created_at' => now(),
                ]);

                return $appointment;
            }, attempts: 3);   // InnoDB deadlock retry.
        } catch (QueryException $e) {
            /*
             | The unique index fired: someone won the race between our capacity
             | check and our insert. Translate it into the same friendly
             | exception the rest of the flow already handles, rather than
             | letting a raw SQL error reach the patient as a 500.
             |
             | 23000 is the SQLSTATE for an integrity constraint violation,
             | which is portable across MySQL, MariaDB and SQLite — unlike the
             | driver-specific 1062.
             */
            if ($e->getCode() === '23000') {
                throw SlotUnavailableException::justTaken($data->startsAt);
            }

            throw $e;
        }
    }

    /**
     * Free seats whose payment window has passed.
     *
     * Done here, lazily, as well as by the appointments:release-unpaid command.
     * Most buyers run on shared hosting with no cron, and a seat that the
     * availability page has already stopped counting must not still be blocking
     * the insert.
     */
    private function releaseExpiredHolds(Slot $slot): void
    {
        Appointment::query()
            ->where('starts_at', $slot->startsAt->utc())
            ->expiredHolds()
            ->get()
            ->each(function (Appointment $appointment): void {
                $appointment->markStatus(AppointmentStatus::Cancelled);
                $appointment->cancelled_at = now();
                $appointment->cancellation_reason = 'Payment was not completed in time.';
                $appointment->hold_expires_at = null;
                $appointment->save();
            });
    }

    /**
     * Stop the same patient booking the same time twice.
     *
     * A double-submitted form or an impatient second click, not an attack —
     * but the result is two appointments the chamber has to untangle.
     *
     * @throws SlotUnavailableException
     */
    private function guardAgainstDuplicate(BookingData $data, Slot $slot): void
    {
        $exists = Appointment::query()
            ->where('patient_id', $data->patient->getKey())
            ->where('starts_at', $slot->startsAt->utc())
            ->blocking()
            ->exists();

        if ($exists) {
            throw SlotUnavailableException::alreadyBooked();
        }
    }

    /**
     * The lowest free seat number in the slot.
     *
     * Lowest rather than next: a cancellation leaves a gap, and reusing it
     * keeps a serial-system chamber's numbers contiguous, which is how the
     * patients standing in the waiting room expect them to work.
     *
     * @throws SlotUnavailableException
     */
    private function allocateSeat(Slot $slot): int
    {
        $taken = Appointment::query()
            ->where('starts_at', $slot->startsAt->utc())
            ->blocking()
            ->lockForUpdate()
            ->pluck('seat_no')
            ->all();

        $free = collect(range(1, max(1, $slot->capacity)))->diff($taken)->first();

        return $free ?? throw SlotUnavailableException::justTaken($slot->startsAt);
    }

    /**
     * What a fresh booking starts as.
     *
     * Configurable because chambers differ: most want to ring the patient back
     * and confirm by hand, some have a schedule reliable enough to guarantee
     * the slot immediately. A successful payment always confirms regardless —
     * the patient has paid, the slot is theirs.
     */
    private function initialStatus(): AppointmentStatus
    {
        return AppointmentStatus::tryFrom((string) config('booking.default_status'))
            ?? AppointmentStatus::Pending;
    }
}
