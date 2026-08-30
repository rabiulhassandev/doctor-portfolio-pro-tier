<?php

namespace App\Notifications;

use App\Enums\BookingActor;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Notifications\Concerns\SendsBySms;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your appointment has moved" — one message, not a cancellation plus a
 * booking.
 *
 * Rescheduling creates a new appointment row and closes the old one, but the
 * patient should never see that plumbing. Two emails arriving a second apart,
 * one cancelling and one confirming, reads like the system made a mistake and
 * corrected itself — and earns the chamber a phone call.
 *
 * Add `implements ShouldQueue` if you run a queue worker.
 */
class AppointmentRescheduledPatient extends Notification
{
    use SendsBySms;

    public function __construct(
        public readonly Appointment $original,
        public readonly Appointment $replacement,
        public readonly BookingActor $actor,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $doctor = DoctorProfile::current();

        $mail = (new MailMessage)
            ->subject(sprintf(
                '%s — your appointment has moved to %s',
                $doctor->name ?: config('site.name'),
                $this->replacement->startsAtLocal()->format('j F'),
            ))
            ->greeting('Hello '.$this->replacement->patient_name.',')
            ->line('Your appointment has been moved. Please note the new time:')
            ->line('**Was:** '.$this->original->dateLabel().', '.$this->original->timeLabel())
            ->line('**Now:** '.$this->replacement->dateLabel().', '.$this->replacement->timeLabel())
            ->line('**Booking number:** '.$this->replacement->reference
                .' (this replaces '.$this->original->reference.')');

        if (filled($doctor->fullAddress())) {
            $mail->line('**Where:** '.$doctor->fullAddress());
        }

        if (filled($this->original->cancellation_reason) && $this->actor !== BookingActor::Patient) {
            $mail->line('**Reason:** '.$this->original->cancellation_reason);
        }

        return $mail
            ->action('View your appointment', route('patient.appointments.show', $this->replacement))
            ->line('If the new time does not suit you, please get in touch and we will find another.')
            ->salutation('— '.($doctor->chamber_name ?: $doctor->name ?: config('site.name')));
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'Your appointment has moved to %s at %s. New ref %s.',
            $this->replacement->startsAtLocal()->format('j M'),
            $this->replacement->startsAtLocal()->format('g:ia'),
            $this->replacement->reference,
        );
    }
}
