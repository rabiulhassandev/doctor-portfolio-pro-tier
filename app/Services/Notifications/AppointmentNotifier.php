<?php

namespace App\Services\Notifications;

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Payment;
use App\Notifications\AppointmentBookedDoctor;
use App\Notifications\AppointmentBookedPatient;
use App\Notifications\AppointmentReminderPatient;
use App\Notifications\AppointmentRescheduledPatient;
use App\Notifications\AppointmentStatusChangedDoctor;
use App\Notifications\AppointmentStatusChangedPatient;
use App\Notifications\PaymentReceiptPatient;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

/**
 * Everything that tells a human something happened to an appointment.
 *
 * ===========================================================================
 * ONE GREPPABLE ANSWER TO "WHAT SENDS EMAIL HERE?"
 * ===========================================================================
 *
 * Notifications are triggered by explicit calls from BookingService,
 * AppointmentWorkflow and PaymentProcessor — never by model events. The
 * reasoning is written out in full in AppointmentWorkflow's doc block; the
 * short version is that observers fire for seeders and cannot tell you who
 * acted.
 *
 * ===========================================================================
 * NOTHING IN HERE MAY THROW
 * ===========================================================================
 *
 * A half-configured mail server must never cost a patient their appointment.
 * Every send goes through dispatch(), which swallows and logs. The booking is
 * already committed by the time any of this runs.
 */
final class AppointmentNotifier
{
    /** A patient has just booked. Tell them, and tell the chamber. */
    public function booked(Appointment $appointment): void
    {
        $this->toPatient($appointment, new AppointmentBookedPatient($appointment));
        $this->toDoctor(new AppointmentBookedDoctor($appointment));
    }

    /**
     * An appointment moved to a new status.
     *
     * The chamber is told only when somebody else caused it. Emailing the
     * doctor about the button they just pressed is noise, and a practice that
     * learns to ignore its notifications will eventually ignore an important
     * one.
     */
    public function statusChanged(Appointment $appointment, AppointmentStatus $from, BookingActor $actor): void
    {
        $this->toPatient(
            $appointment,
            new AppointmentStatusChangedPatient($appointment, $from, $actor),
        );

        if ($actor !== BookingActor::Admin) {
            $this->toDoctor(new AppointmentStatusChangedDoctor($appointment, $from, $actor));
        }
    }

    /** An appointment was moved to a different time. One message, not two. */
    public function rescheduled(Appointment $original, Appointment $replacement, BookingActor $actor): void
    {
        $this->toPatient(
            $replacement,
            new AppointmentRescheduledPatient($original, $replacement, $actor),
        );

        if ($actor !== BookingActor::Admin) {
            $this->toDoctor(new AppointmentStatusChangedDoctor(
                $replacement,
                $original->status,
                $actor,
            ));
        }
    }

    /** Money arrived. The confirmation email is sent separately by the workflow. */
    public function paymentReceived(Payment $payment): void
    {
        $this->toPatient($payment->appointment, new PaymentReceiptPatient($payment));
    }

    /** Sent by the appointments:send-reminders command. */
    public function reminder(Appointment $appointment): void
    {
        $this->toPatient($appointment, new AppointmentReminderPatient($appointment));
    }

    // -----------------------------------------------------------------------
    // Delivery
    // -----------------------------------------------------------------------

    /**
     * Send to the patient, using the contact details snapshotted on the
     * appointment rather than the ones currently on their account.
     *
     * If a patient changed their email yesterday, the confirmation for an
     * appointment booked last week should still go where it was promised.
     */
    private function toPatient(Appointment $appointment, Notification $notification): void
    {
        $this->dispatch(
            $appointment->patient_email,
            $appointment->patient_phone,
            $notification,
        );
    }

    /**
     * Send to the chamber.
     *
     * Falls back to the address mail is sent *from*, which is always set —
     * better a copy in the site's own inbox than a notification that silently
     * goes nowhere because the profile was left half-filled.
     */
    private function toDoctor(Notification $notification): void
    {
        $doctor = DoctorProfile::current();

        $this->dispatch(
            $doctor->notificationEmail(),
            $doctor->phone,
            $notification,
        );
    }

    /**
     * The one place anything is actually sent.
     *
     * On-demand routing rather than notifying a model, because the recipient is
     * an address and a number rather than an account — the doctor has no
     * notifiable record at all, and the patient's details are snapshotted.
     */
    private function dispatch(?string $email, ?string $phone, Notification $notification): void
    {
        if (blank($email) && blank($phone)) {
            return;
        }

        try {
            Notifier::route('mail', $email ?: null)
                ->route('sms', $phone ?: null)
                ->notify($notification);
        } catch (Throwable $e) {
            /*
             | Swallowed on purpose. By the time this runs the appointment is
             | committed, and there is nothing useful to tell the patient about
             | a failed SMTP handshake — telling them the booking failed would
             | be a lie, and they would book again.
             */
            Log::error('An appointment notification could not be sent.', [
                'notification' => $notification::class,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
