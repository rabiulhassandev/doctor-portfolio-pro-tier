<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * The default SMS sender: writes to the log and sends nothing.
 *
 * This is not a stub in the "unfinished" sense — it is the correct behaviour
 * for a template that ships without a paid account. A developer setting the
 * site up can watch storage/logs/laravel.log and see exactly which messages
 * would have gone out, to which number, with what wording, before spending a
 * taka on a gateway.
 *
 * isConfigured() returns false, so notifications skip the SMS channel entirely
 * rather than pretending a text was sent.
 *
 * @see SmsSender
 */
final class NullSmsSender implements SmsSender
{
    public function send(string $to, string $message): bool
    {
        Log::info('[SMS stub] No SMS provider is configured, so this message was not sent.', [
            'to' => $to,
            'message' => $message,
            'how_to_enable' => 'See app/Contracts/SmsSender.php and config/booking.php.',
        ]);

        // True: the message was handled as configured. The caller has no
        // failure to report, because nothing was expected to be sent.
        return true;
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
