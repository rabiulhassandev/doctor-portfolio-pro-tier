<?php

namespace App\Notifications;

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use App\Models\Appointment;
use App\Notifications\Concerns\SendsBySms;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "A patient has changed their appointment" — sent to the chamber.
 *
 * Only ever sent when somebody OTHER than the staff made the change; see
 * AppointmentNotifier::statusChanged(). Emailing the doctor to tell them what
 * they just did in the admin panel is noise, and noise is how a practice
 * learns to ignore its own notifications.
 *
 * Add `implements ShouldQueue` if you run a queue worker.
 */
class AppointmentStatusChangedDoctor extends Notification
{
    use SendsBySms;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly AppointmentStatus $from,
        public readonly BookingActor $actor,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $isCancellation = $this->appointment->status === AppointmentStatus::Cancelled;

        $mail = (new MailMessage)
            ->subject(sprintf(
                '%s: %s, %s',
                $isCancellation ? 'Cancellation' : 'Appointment update',
                $this->appointment->patient_name,
                $this->appointment->startsAtLocal()->format('j M, g:i A'),
            ))
            ->greeting($isCancellation ? 'A patient has cancelled' : 'An appointment has changed')
            ->line('**Patient:** '.$this->appointment->patient_name)
            ->line('**Phone:** '.$this->appointment->patient_phone)
            ->line('**When:** '.$this->appointment->dateLabel().', '.$this->appointment->timeLabel())
            ->line('**Now:** '.$this->appointment->status->getLabel())
            ->line('**Changed by:** '.$this->actor->label());

        if (filled($this->appointment->cancellation_reason)) {
            $mail->line('**Reason given:** '.$this->appointment->cancellation_reason);
        }

        if ($isCancellation) {
            $mail->line('That slot is now free for another patient to book.');
        }

        return $mail->action(
            'View in the admin panel',
            url('/admin/appointments/'.$this->appointment->getRouteKey()),
        );
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            '%s cancelled %s %s. Ref %s.',
            $this->appointment->patient_name,
            $this->appointment->startsAtLocal()->format('j M'),
            $this->appointment->startsAtLocal()->format('g:ia'),
            $this->appointment->reference,
        );
    }
}
