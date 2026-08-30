<?php

namespace App\Notifications;

use App\Models\DoctorProfile;
use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "We have received your payment."
 *
 * Email only — no SMS. A receipt is something a patient keeps and may need to
 * show, and squeezing a transaction id into 160 characters helps nobody.
 *
 * Add `implements ShouldQueue` if you run a queue worker.
 */
class PaymentReceiptPatient extends Notification
{
    public function __construct(public readonly Payment $payment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $doctor = DoctorProfile::current();
        $appointment = $this->payment->appointment;

        return (new MailMessage)
            ->subject(sprintf(
                'Payment received — %s',
                $doctor->name ?: config('site.name'),
            ))
            ->greeting('Hello '.$appointment->patient_name.',')
            ->line('Thank you. We have received your payment and your appointment is confirmed.')
            ->line('**Amount paid:** '.$this->payment->formattedAmount())
            ->line('**Paid by:** '.$this->payment->gatewayLabel())
            ->line('**Transaction reference:** '.$this->payment->reference)
            ->line('**Appointment:** '.$appointment->dateLabel().', '.$appointment->timeLabel())
            ->line('**Booking number:** '.$appointment->reference)
            ->action('View your appointment', route('patient.appointments.show', $appointment))
            ->line('Please keep this email as your receipt.')
            ->salutation('— '.($doctor->chamber_name ?: $doctor->name ?: config('site.name')));
    }
}
