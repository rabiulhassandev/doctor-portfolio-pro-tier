<?php

namespace App\Contracts;

use App\Notifications\Channels\SmsChannel;
use App\Services\Sms\ExampleHttpSmsSender;
use App\Services\Sms\NullSmsSender;

/**
 * Sends a short message: SMS, WhatsApp, whatever your provider offers.
 *
 * ===========================================================================
 * >>> THIS IS THE SMS / WHATSAPP INTEGRATION POINT. <<<
 * ===========================================================================
 *
 * The template deliberately ships with NO paid gateway. Every country has a
 * different set of providers, prices and sender-id rules, and hard-coding one
 * would mean most buyers deleting code before they could start.
 *
 * To go live:
 *
 *   1. Write a class implementing this interface. The whole thing is two
 *      methods — see App\Services\Sms\ExampleHttpSmsSender for a complete,
 *      working REST implementation you can copy and adjust.
 *
 *   2. Register it in App\Providers\AppServiceProvider::register(), where the
 *      binding already switches on config('booking.sms.driver').
 *
 *   3. Set SMS_ENABLED=true and SMS_DRIVER=<your key> in .env.
 *
 * Every appointment notification then starts going out by text as well as by
 * email, with no other change anywhere in the application.
 *
 * WhatsApp needs no separate interface. A class wrapping the WhatsApp Cloud
 * API satisfies this contract exactly as an SMS provider would — the only
 * difference is the endpoint you post to.
 *
 * @see NullSmsSender          The default: logs, sends nothing.
 * @see ExampleHttpSmsSender   A generic REST example.
 * @see SmsChannel   How notifications reach this.
 */
interface SmsSender
{
    /**
     * Deliver one message.
     *
     * Implementations must not throw. A provider being down is not a reason a
     * patient loses their appointment — return false and log, and the caller
     * carries on. App\Services\Notifications\AppointmentNotifier depends on
     * this being true.
     *
     * @param  string  $to  Destination number, ideally in E.164 form (+8801…).
     * @return bool True when the provider accepted the message.
     */
    public function send(string $to, string $message): bool;

    /**
     * Whether this sender has everything it needs to work.
     *
     * False when credentials are missing, so callers skip silently instead of
     * erroring. A half-configured install should be quiet, not broken.
     */
    public function isConfigured(): bool;
}
