<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;

/**
 * Who may look at and act on an appointment.
 *
 * Two audiences on two different guards, so every method takes the actor as a
 * union type. Laravel resolves this policy automatically from the model name.
 *
 * Note what these methods do NOT decide: whether a change is *legal* (that is
 * AppointmentStatus::allowedTransitions) or whether there is enough notice
 * (that is AppointmentWorkflow). This file answers only "is this person allowed
 * near this record at all".
 */
class AppointmentPolicy
{
    /**
     * Staff can see everything.
     *
     * Every row in `users` is staff — there is no public registration for that
     * table, and accounts are made with `php artisan make:filament-user`. A
     * buyer adding a receptionist role has one obvious place to change this.
     */
    public function viewAny(User|Patient $actor): bool
    {
        return $actor instanceof User;
    }

    public function view(User|Patient $actor, Appointment $appointment): bool
    {
        if ($actor instanceof User) {
            return true;
        }

        return $actor->getKey() === $appointment->patient_id;
    }

    /**
     * Only the patient it belongs to may cancel it from the website.
     *
     * Whether the cutoff has passed is checked separately, by
     * AppointmentWorkflow, so that the reason for refusing can be explained
     * rather than merely denied.
     */
    public function cancel(User|Patient $actor, Appointment $appointment): bool
    {
        if ($actor instanceof User) {
            return true;
        }

        return $actor->getKey() === $appointment->patient_id;
    }

    /**
     * Nobody creates appointments through a policy check.
     *
     * They are made by BookingService, which allocates a seat and enforces
     * capacity. Bypassing it is how two patients end up with the same time.
     */
    public function create(User|Patient $actor): bool
    {
        return false;
    }

    /** Appointments are changed through the workflow, never edited directly. */
    public function update(User|Patient $actor, Appointment $appointment): bool
    {
        return false;
    }

    /**
     * Never deleted.
     *
     * An appointment is a record of something that happened, and payments hang
     * off it. Cancelling is the operation; deleting is not.
     */
    public function delete(User|Patient $actor, Appointment $appointment): bool
    {
        return false;
    }
}
