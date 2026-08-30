<?php

namespace App\Notifications;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Notifications\Concerns\SendsBySms;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "We have your booking" — sent to the patient the moment they book.
 *
 * Sent synchronously, like every notification in this template, because most
 * buyers run on shared hosting with no queue worker and a queued mail there
 * would sit unsent forever. If you do run `php artisan queue:work`, add one
 * interface and nothing else changes:
 *
 *     class AppointmentBookedPatient extends Notification implements ShouldQueue
 */
class AppointmentBookedPatient extends Notification
{
    use SendsBySms;

    public function __construct(public readonly Appointment $appointment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $doctor = DoctorProfile::current();
        $isConfirmed = $this->appointment->status === AppointmentStatus::Confirmed;

        $mail = (new MailMessage)
            ->subject(sprintf(
                '%s — your appointment on %s',
                $doctor->name ?: config('site.name'),
                $this->appointment->startsAtLocal()->format('j F'),
            ))
            ->greeting('Hello '.$this->appointment->patient_name.',')
            ->line($isConfirmed
                ? 'Your appointment is confirmed. Here are the details:'
                : 'Thank you — we have received your booking request. The chamber will confirm it shortly.')
            ->line('**Date:** '.$this->appointment->dateLabel())
            ->line('**Time:** '.$this->appointment->timeLabel())
            ->line('**Booking number:** '.$this->appointment->reference);

        if ($fee = $this->appointment->formattedFee()) {
            $mail->line('**Consultation fee:** '.$fee
                .($this->appointment->isUnpaid() ? ' (payable at the chamber)' : ' — paid, thank you'));
        }

        if (filled($doctor->fullAddress())) {
            $mail->line('**Where:** '.$doctor->fullAddress());
        }

        if (filled($doctor->booking_instructions)) {
            $mail->line($doctor->booking_instructions);
        }

        $mail->action('View your appointment', route('patient.appointments.show', $this->appointment));

        // Only offer the link if pressing it would actually work — an
        // appointment inside the cancellation window cannot be called off
        // online, and a dead-end link is worse than none.
        if ($this->appointment->isCancellableByPatient()) {
            $mail->line('Need to change or cancel? You can do that from your patient account.');
        } elseif (filled($doctor->phone)) {
            $mail->line('If you need to change anything, please telephone '.$doctor->phone.'.');
        }

        return $mail->salutation('— '.($doctor->chamber_name ?: $doctor->name ?: config('site.name')));
    }

    /** Kept to one segment where possible: SMS is billed per 160 characters. */
    public function toSms(object $notifiable): string
    {
        return sprintf(
            '%s: appointment %s on %s at %s. Ref %s.',
            $this->appointment->status === AppointmentStatus::Confirmed ? 'Confirmed' : 'Requested',
            $this->appointment->patient_name,
            $this->appointment->startsAtLocal()->format('j M'),
            $this->appointment->startsAtLocal()->format('g:ia'),
            $this->appointment->reference,
        );
    }
}
