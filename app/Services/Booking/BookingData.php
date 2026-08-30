<?php

namespace App\Services\Booking;

use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Support\Clock;
use App\Support\Slot;
use Carbon\CarbonImmutable;

/**
 * Everything needed to make one booking.
 *
 * A small object rather than a long argument list, so the Livewire wizard, the
 * reschedule path and the tests all hand BookingService the same shape.
 *
 * Note what is NOT here: capacity, duration, seat number and the fee are all
 * derived server-side at booking time. The caller supplies a patient and a
 * time; everything else is worked out from the availability rules and the
 * doctor's profile. Anything the browser could have tampered with is
 * deliberately absent.
 */
final readonly class BookingData
{
    public function __construct(
        public Patient $patient,
        /** Start of the appointment, in clinic time. */
        public CarbonImmutable $startsAt,
        /** What the patient wrote in the "anything we should know?" box. */
        public ?string $notes = null,
    ) {}

    /**
     * Build from what the booking form posted.
     *
     * The form sends a plain 'Y-m-d H:i' string, parsed here as clinic time —
     * never as UTC. See App\Support\Clock for why that distinction matters.
     */
    public static function fromForm(Patient $patient, string $slotKey, ?string $notes = null): self
    {
        return new self(
            patient: $patient,
            startsAt: Clock::parse($slotKey),
            notes: $notes,
        );
    }

    /**
     * The row to insert, given the slot the service resolved and the seat it
     * allocated.
     *
     * The patient's contact details are snapshotted rather than left to the
     * relationship: this appointment is a record of what happened, and a phone
     * number changed next year must not rewrite the number the chamber rang
     * last month.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Slot $slot, int $seatNo): array
    {
        $doctor = DoctorProfile::current();

        return [
            'patient_id' => $this->patient->getKey(),
            'patient_name' => $this->patient->name,
            'patient_email' => $this->patient->email,
            'patient_phone' => $this->patient->phone,
            'starts_at' => $slot->startsAt->utc(),
            'ends_at' => $slot->endsAt->utc(),
            'seat_no' => $seatNo,
            'notes' => $this->notes,
            // Snapshotted for the same reason as the contact details: raising
            // the fee next month must not rewrite what this patient was told.
            'fee_amount' => $doctor->consultation_fee,
            'currency' => $doctor->hasFee() ? config('booking.payment.currency', 'BDT') : null,
        ];
    }
}
