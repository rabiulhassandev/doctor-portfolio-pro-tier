<?php

namespace App\Notifications\Channels;

use App\Contracts\SmsSender;
use Illuminate\Notifications\Notification;

/**
 * Delivers notifications through whatever SMS or WhatsApp provider is
 * configured.
 *
 * A proper Laravel channel rather than a manual call from the notifier,
 * because it is fifteen lines and it is the shape a developer will find in the
 * Laravel documentation when they come to extend it.
 *
 * A notification opts in by returning this class from via() and defining a
 * toSms() method. Anything without one is skipped silently — that is what
 * lets, say, a payment receipt be email-only without any branching here.
 *
 * @see SmsSender  The integration point itself.
 */
final class SmsChannel
{
    public function __construct(private readonly SmsSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $to = $notifiable->routeNotificationFor('sms', $notification);

        if (blank($to) || ! method_exists($notification, 'toSms')) {
            return;
        }

        $message = trim((string) $notification->toSms($notifiable));

        if ($message === '') {
            return;
        }

        // The sender is contractually forbidden from throwing, so a provider
        // being down cannot break the request that triggered the notification.
        $this->sender->send($to, $message);
    }
}
