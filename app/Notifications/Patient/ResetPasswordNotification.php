<?php

namespace App\Notifications\Patient;

use App\Models\DoctorProfile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Reset your password" for the patient guard.
 *
 * Laravel's built-in version builds a link to the *staff* reset form, because
 * it knows nothing about the second guard. This one points at the patient form
 * instead, which is the only reason it exists.
 *
 * Sent synchronously, like every notification in this template. Add
 * `implements ShouldQueue` if you run a queue worker; nothing else changes.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(
        #[\SensitiveParameter]
        private readonly string $token,
    ) {}

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
        $minutes = config('auth.passwords.patients.expire', 60);

        $url = url(route('patient.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));

        return (new MailMessage)
            ->subject('Reset your password — '.($doctor->name ?: config('site.name')))
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We received a request to reset the password on your patient account.')
            ->action('Choose a new password', url($url))
            ->line("This link stops working in {$minutes} minutes.")
            ->line('If you did not ask for this, you can safely ignore this email — your password will not change.')
            ->salutation('— '.($doctor->chamber_name ?: $doctor->name ?: config('site.name')));
    }
}
