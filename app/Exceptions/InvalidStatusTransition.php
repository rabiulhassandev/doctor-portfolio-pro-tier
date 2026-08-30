<?php

namespace App\Exceptions;

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use DomainException;

/**
 * Somebody tried to move an appointment somewhere it cannot go.
 *
 * Unlike SlotUnavailableException — which is a normal thing to happen — this
 * generally means a bug or a stale page: a receptionist pressing "Confirm" on
 * a screen loaded before the patient cancelled, or code taking a shortcut past
 * AppointmentWorkflow.
 *
 * The legal moves are defined by AppointmentStatus::allowedTransitions().
 */
class InvalidStatusTransition extends DomainException
{
    public static function between(AppointmentStatus $from, AppointmentStatus $to): self
    {
        return new self(sprintf(
            'This appointment is %s, so it cannot be changed to %s.',
            mb_strtolower($from->getLabel()),
            mb_strtolower($to->getLabel()),
        ));
    }

    /** The move is legal, but not for this person. */
    public static function forActor(BookingActor $actor, AppointmentStatus $to): self
    {
        return new self(sprintf(
            'A %s may not change an appointment to %s.',
            mb_strtolower($actor->value),
            mb_strtolower($to->getLabel()),
        ));
    }

    /** The patient tried to cancel inside the notice period. */
    public static function tooLateToCancel(int $hours): self
    {
        return new self(sprintf(
            'Appointments can only be cancelled online more than %d hours in advance. '
            .'Please telephone the chamber instead.',
            $hours,
        ));
    }
}
