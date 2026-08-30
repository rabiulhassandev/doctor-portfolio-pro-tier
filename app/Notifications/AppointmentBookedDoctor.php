<?php

namespace App\Notifications;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Notifications\Concerns\SendsBySms;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "A patient has booked" — sent to the chamber.
 *
 * Written for someone glancing at a phone between patients, so the phone
 * number comes before the prose: the single most likely next action is ringing
 * the patient back.
 *
 * Add `implements ShouldQueue` if you run a queue worker.
 */
class AppointmentBookedDoctor extends Notification
{
    use SendsBySms;

    public function __construct(public readonly Appointment $appointment) {}

    public function toMail(object $notifiable): MailMessage
    {
        $needsConfirming = $this->appointment->status === AppointmentStatus::Pending;

        $mail = (new MailMessage)
            ->subject(sprintf(
                '%s appointment: %s, %s',
                $needsConfirming ? 'New' : 'New confirmed',
                $this->appointment->patient_name,
                $this->appointment->startsAtLocal()->format('j M, g:i A'),
            ))
            ->greeting('New booking')
            ->line('**Patient:** '.$this->appointment->patient_name)
            ->line('**Phone:** '.$this->appointment->patient_phone)
            ->line('**When:** '.$this->appointment->dateLabel().', '.$this->appointment->timeLabel())
            ->line('**Booking number:** '.$this->appointment->reference);

        if (filled($this->appointment->patient_email)) {
            $mail->line('**Email:** '.$this->appointment->patient_email);
        }

        if (filled($this->appointment->notes)) {
            $mail->line('**The patient wrote:** '.$this->appointment->notes);
        }

        if ($fee = $this->appointment->formattedFee()) {
            $mail->line('**Fee:** '.$fee.($this->appointment->isUnpaid() ? ' — not yet paid' : ' — paid online'));
        }

        return $mail
            ->action(
                $needsConfirming ? 'Review and confirm' : 'View in the admin panel',
                url('/admin/appointments/'.$this->appointment->getRouteKey()),
            )
            ->line($needsConfirming
                ? 'This booking is waiting for your confirmation.'
                : 'No action needed — this booking is already confirmed.');
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'New booking: %s, %s %s. Tel %s. Ref %s.',
            $this->appointment->patient_name,
            $this->appointment->startsAtLocal()->format('j M'),
            $this->appointment->startsAtLocal()->format('g:ia'),
            $this->appointment->patient_phone,
            $this->appointment->reference,
        );
    }
}
