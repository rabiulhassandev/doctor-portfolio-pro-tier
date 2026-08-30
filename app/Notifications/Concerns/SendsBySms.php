<?php

namespace App\Notifications\Concerns;

use App\Contracts\SmsSender;
use App\Notifications\Channels\SmsChannel;

/**
 * Adds the SMS channel to a notification, but only when it can actually work.
 *
 * Every appointment notification wants the same rule: send by email always,
 * and by text as well if — and only if — the buyer has switched SMS on *and*
 * a real provider is configured. Writing that out in six notification classes
 * would guarantee they eventually disagree.
 *
 * A class using this defines a `toSms(): string` method and nothing else.
 */
trait SendsBySms
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if ($this->smsIsAvailable()) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    /**
     * Both halves matter.
     *
     * `enabled` is the buyer's choice. `isConfigured()` is whether it would
     * work — the default NullSmsSender returns false, so a fresh install never
     * pretends to have sent a text it did not send.
     */
    protected function smsIsAvailable(): bool
    {
        return (bool) config('booking.sms.enabled')
            && app(SmsSender::class)->isConfigured();
    }
}
