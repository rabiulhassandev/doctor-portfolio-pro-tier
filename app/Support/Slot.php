<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * One bookable appointment time.
 *
 * Slots are *derived*, never stored: they are what you get when you expand the
 * doctor's availability rules across a date and subtract what is already
 * booked. Two page loads a second apart can legitimately produce different
 * slots, which is exactly why the result is never cached.
 *
 * Immutable and readonly so nothing downstream can quietly adjust a time or a
 * capacity after the service has worked it out.
 *
 * Built by `App\Services\Booking\SlotGenerator` and handed out by
 * `App\Services\Booking\AvailabilityService`. Those are named in prose rather
 * than in `@see` tags on purpose: the formatter turns a docblock class
 * reference into a real `use` statement, and App\Support must not depend on
 * the service layer. See tests/Feature/ArchitectureTest.php.
 */
final readonly class Slot
{
    public function __construct(
        /** Start of the appointment, in clinic time. */
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        /** How many patients this time can take in total. */
        public int $capacity,
        /** How many of those places are already gone. */
        public int $booked,
        /** The availability rule this slot came from. */
        public int $availabilitySlotId,
    ) {}

    public function remaining(): int
    {
        return max(0, $this->capacity - $this->booked);
    }

    public function isBookable(): bool
    {
        return $this->remaining() > 0;
    }

    /**
     * The value the booking form posts back, e.g. "2026-09-12 18:30".
     *
     * Deliberately just a time. The form never posts a slot id, a capacity, a
     * duration or a price — the server re-derives all of those through
     * AvailabilityService::findBookableSlot(). Anything the browser sends is
     * something an attacker can change.
     */
    public function key(): string
    {
        return $this->startsAt->format('Y-m-d H:i');
    }

    /** "6:00 PM" — what the patient taps. */
    public function label(): string
    {
        return $this->startsAt->format('g:i A');
    }

    /** "6:00 PM – 6:30 PM" — for confirmations and emails. */
    public function rangeLabel(): string
    {
        return $this->startsAt->format('g:i A').' – '.$this->endsAt->format('g:i A');
    }

    public function durationMinutes(): int
    {
        return (int) $this->startsAt->diffInMinutes($this->endsAt);
    }

    /**
     * "2 places left" — shown only when a slot is filling up.
     *
     * Silent while there is plenty of room, because a counter on every slot
     * reads as a pressure tactic rather than as information. Null means say
     * nothing.
     */
    public function scarcityLabel(): ?string
    {
        if ($this->capacity <= 1 || $this->remaining() > 2 || ! $this->isBookable()) {
            return null;
        }

        return $this->remaining() === 1
            ? 'Last place'
            : $this->remaining().' places left';
    }
}
