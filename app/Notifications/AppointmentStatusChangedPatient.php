<?php

namespace App\Notifications;

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Notifications\Concerns\SendsBySms;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your appointment has changed" — sent to the patient.
 *
 * The wording depends on WHO made the change, which is the whole reason
 * BookingActor is threaded through AppointmentWorkflow. "You cancelled your
 * appointment" and "the chamber has cancelled your appointment" are entirely
 * different messages to receive, and a system that cannot tell them apart has
 * to send something vague instead.
 *
 * Add `implements ShouldQueue` if you run a queue worker.
 */
class AppointmentStatusChangedPatient extends Notification
{
    use SendsBySms;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly AppointmentStatus $from,
        public readonly BookingActor $actor,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $doctor = DoctorProfile::current();

        $mail = (new MailMessage)
            ->subject($this->subject($doctor))
            ->greeting('Hello '.$this->appointment->patient_name.',')
            ->line($this->opening())
            ->line('**Date:** '.$this->appointment->dateLabel())
            ->line('**Time:** '.$this->appointment->timeLabel())
            ->line('**Booking number:** '.$this->appointment->reference);

        if ($this->appointment->status === AppointmentStatus::Confirmed) {
            if (filled($doctor->fullAddress())) {
                $mail->line('**Where:** '.$doctor->fullAddress());
            }

            if (filled($doctor->booking_instructions)) {
                $mail->line($doctor->booking_instructions);
            }
        }

        if ($this->appointment->status === AppointmentStatus::Cancelled) {
            if (filled($this->appointment->cancellation_reason) && $this->actor !== BookingActor::Patient) {
                $mail->line('**Reason:** '.$this->appointment->cancellation_reason);
            }

            $mail->action('Book another appointment', route('booking'));

            return $mail->salutation('— '.($doctor->chamber_name ?: $doctor->name ?: config('site.name')));
        }

        return $mail
            ->action('View your appointment', route('patient.appointments.show', $this->appointment))
            ->salutation('— '.($doctor->chamber_name ?: $doctor->name ?: config('site.name')));
    }

    private function subject(DoctorProfile $doctor): string
    {
        $name = $doctor->name ?: config('site.name');
        $date = $this->appointment->startsAtLocal()->format('j F');

        return match ($this->appointment->status) {
            AppointmentStatus::Confirmed => "{$name} — your appointment on {$date} is confirmed",
            AppointmentStatus::Cancelled => "{$name} — your appointment on {$date} has been cancelled",
            AppointmentStatus::Completed => "{$name} — thank you for your visit",
            default => "{$name} — an update about your appointment",
        };
    }

    /** The one line that carries the actual news. */
    private function opening(): string
    {
        return match ($this->appointment->status) {
            AppointmentStatus::Confirmed => 'Good news — the chamber has confirmed your appointment.',

            AppointmentStatus::Cancelled => $this->actor === BookingActor::Patient
                ? 'This confirms that you cancelled the following appointment.'
                : 'We are sorry — the chamber has had to cancel the following appointment.',

            AppointmentStatus::Completed => 'Thank you for coming in. Any prescriptions or reports from your '
                .'visit will appear in your patient account.',

            default => 'There has been an update to your appointment.',
        };
    }

    public function toSms(object $notifiable): string
    {
        return match ($this->appointment->status) {
            AppointmentStatus::Confirmed => sprintf(
                'Confirmed: %s at %s. Ref %s.',
                $this->appointment->startsAtLocal()->format('j M'),
                $this->appointment->startsAtLocal()->format('g:ia'),
                $this->appointment->reference,
            ),
            AppointmentStatus::Cancelled => sprintf(
                'Cancelled: your appointment on %s at %s. Ref %s.',
                $this->appointment->startsAtLocal()->format('j M'),
                $this->appointment->startsAtLocal()->format('g:ia'),
                $this->appointment->reference,
            ),
            default => sprintf(
                'Your appointment %s is now %s.',
                $this->appointment->reference,
                mb_strtolower($this->appointment->status->getLabel()),
            ),
        };
    }
}
