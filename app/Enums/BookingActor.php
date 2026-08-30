<?php

namespace App\Enums;

/**
 * Who caused a change to an appointment.
 *
 * Every call into App\Services\Booking\AppointmentWorkflow carries one of
 * these. It does two jobs:
 *
 *   1. The audit trail. A clinic *will* eventually argue about who cancelled
 *      what, and "the patient cancelled at 2pm" is the answer that ends it.
 *
 *   2. The wording of the email. "You cancelled your appointment" and "the
 *      chamber has cancelled your appointment" are very different messages to
 *      receive, and a system that cannot tell them apart has to send something
 *      vague and unhelpful instead.
 */
enum BookingActor: string
{
    /** A staff member working in the admin panel. */
    case Admin = 'admin';

    /** The patient, from their own dashboard or the booking flow. */
    case Patient = 'patient';

    /**
     * The application itself: a payment callback confirming a booking, or the
     * scheduled command releasing a slot whose payment was never completed.
     */
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'The clinic',
            self::Patient => 'The patient',
            self::System => 'Automatic',
        };
    }
}
