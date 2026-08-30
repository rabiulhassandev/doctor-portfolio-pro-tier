<?php

namespace App\Exceptions;

use App\Support\Clock;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * The requested appointment time cannot be booked.
 *
 * This is an expected outcome, not a fault. Two patients looking at the last
 * place at the same moment is normal, and one of them has to be told so — in
 * words a patient understands, which is what getMessage() carries.
 *
 * Callers should catch this and re-render the slot grid, never let it become
 * a 500.
 */
class SlotUnavailableException extends RuntimeException
{
    /** The time is not on offer at all: no rule covers it, or the day is closed. */
    public static function forTime(CarbonImmutable $startsAt): self
    {
        return new self(sprintf(
            'Sorry, %s on %s is no longer available. Please choose another time.',
            Clock::fromStorage($startsAt)->format('g:i A'),
            Clock::fromStorage($startsAt)->format('l j F'),
        ));
    }

    /**
     * The time existed and was free a moment ago, and now is not.
     *
     * Worth its own message: "somebody just took it" tells the patient to try
     * again immediately, whereas "not available" suggests it never was.
     */
    public static function justTaken(CarbonImmutable $startsAt): self
    {
        return new self(sprintf(
            'Someone else booked %s just before you did. Please pick another time.',
            Clock::fromStorage($startsAt)->format('g:i A'),
        ));
    }

    /** The patient already holds this exact appointment. */
    public static function alreadyBooked(): self
    {
        return new self('You already have an appointment at this time.');
    }
}
