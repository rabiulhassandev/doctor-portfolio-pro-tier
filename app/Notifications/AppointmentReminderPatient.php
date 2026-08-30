<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Notifications\Concerns\SendsBySms;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your appointment is tomorrow."
 *
 * Sent by the `appointments:send-reminders` command, which needs a scheduler
 * to run. Buyers without cron simply never send these, and nothing else breaks
 * — which is why this is a separate command rather than something the booking
 * flow schedules for itself.
 *
 * Add `implements ShouldQueue` if you run a queue worker.
 */
class AppointmentReminderPatient extends Notification
{
    use SendsBySms;

    public function __construct(public readonly Appointment $appointment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $doctor = DoctorProfile::current();

        $mail = (new MailMessage)
            ->subject(sprintf(
                'Reminder: your appointment %s at %s',
                $this->appointment->startsAtLocal()->isTomorrow() ? 'tomorrow' : 'on '.$this->appointment->startsAtLocal()->format('j F'),
                $this->appointment->startsAtLocal()->format('g:i A'),
            ))
            ->greeting('Hello '.$this->appointment->patient_name.',')
            ->line('This is a reminder of your appointment.')
            ->line('**Date:** '.$this->appointment->dateLabel())
            ->line('**Time:** '.$this->appointment->timeLabel())
            ->line('**Booking number:** '.$this->appointment->reference);

        if (filled($doctor->fullAddress())) {
            $mail->line('**Where:** '.$doctor->fullAddress());
        }

        if (filled($doctor->booking_instructions)) {
            $mail->line($doctor->booking_instructions);
        }

        if ($this->appointment->isUnpaid() && ($fee = $this->appointment->formattedFee())) {
            $mail->line('Please bring the consultation fee of '.$fee.'.');
        }

        return $mail
            ->action('View your appointment', route('patient.appointments.show', $this->appointment))
            ->salutation('— '.($doctor->chamber_name ?: $doctor->name ?: config('site.name')));
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'Reminder: appointment %s at %s. Ref %s.',
            $this->appointment->startsAtLocal()->isTomorrow()
                ? 'tomorrow'
                : $this->appointment->startsAtLocal()->format('j M'),
            $this->appointment->startsAtLocal()->format('g:ia'),
            $this->appointment->reference,
        );
    }
}
