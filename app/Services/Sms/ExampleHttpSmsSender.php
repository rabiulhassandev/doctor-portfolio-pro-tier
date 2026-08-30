<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A complete, working SMS sender for any provider with a simple REST API.
 *
 * ===========================================================================
 * >>> THIS IS THE FILE TO COPY WHEN YOU PLUG IN YOUR OWN SMS PROVIDER. <<<
 * ===========================================================================
 *
 * Deliberately generic: no vendor is named, no package is required, and
 * nothing here is specific to any one country's market. Most bulk-SMS APIs —
 * and every one commonly used in Bangladesh — take an HTTP form post with an
 * API key, a sender id, a destination number and the text. That is all this
 * does.
 *
 * To use it:
 *
 *   1. Set these in .env:
 *          SMS_ENABLED=true
 *          SMS_DRIVER=http
 *          SMS_ENDPOINT=https://your-provider.example/api/send
 *          SMS_API_KEY=…
 *          SMS_SENDER_ID=…
 *
 *   2. Adjust the three lines marked PROVIDER-SPECIFIC below to match your
 *      provider's parameter names and success response.
 *
 * If your provider needs JSON, a bearer token, or a different shape entirely,
 * copy this class rather than contorting it. Writing a second implementation
 * of App\Contracts\SmsSender is the intended path, not a workaround.
 *
 * @see SmsSender
 */
final class ExampleHttpSmsSender implements SmsSender
{
    /**
     * @param  array<string, mixed>  $config  The `sms.http` block of config/booking.php.
     */
    public function __construct(private readonly array $config) {}

    public function send(string $to, string $message): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('[SMS] Skipped: the HTTP sender has no endpoint or API key configured.');

            return false;
        }

        try {
            /*
             | A short timeout, and no retry.
             |
             | Notifications here are sent synchronously, inside the request
             | that booked the appointment. A provider that hangs for thirty
             | seconds would hang the patient's browser for thirty seconds, and
             | a retry would double that. Ten seconds, once, then give up and
             | log — the email has already gone out regardless.
             */
            $response = Http::asForm()
                ->timeout(10)
                ->post($this->config['endpoint'], [
                    // ---- PROVIDER-SPECIFIC: parameter names ----------------
                    // Rename these three to whatever your provider's docs call
                    // them (api_token / to / text, key / msisdn / body, …).
                    'api_key' => $this->config['api_key'],
                    'sender_id' => $this->config['sender_id'] ?? null,
                    'to' => $this->normaliseNumber($to),
                    'message' => $message,
                ]);

            // ---- PROVIDER-SPECIFIC: what counts as success -----------------
            // Many providers return HTTP 200 with a failure code in the body.
            // Check the field your provider actually uses, e.g.
            //     $response->json('status') === 'SUCCESS'
            $accepted = $response->successful();

            if (! $accepted) {
                Log::error('[SMS] The provider rejected the message.', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $accepted;
        } catch (Throwable $e) {
            /*
             | Never rethrow. The contract promises this method does not throw,
             | and an unreachable SMS provider must not cost a patient their
             | appointment.
             */
            Log::error('[SMS] Could not reach the provider.', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function isConfigured(): bool
    {
        return filled($this->config['endpoint'] ?? null)
            && filled($this->config['api_key'] ?? null);
    }

    /**
     * Strip the punctuation people type into phone fields.
     *
     * Left deliberately conservative: it removes spaces, dashes and brackets
     * but does not try to guess a country code. Prefixing a bare local number
     * with the wrong country would send the message to a stranger, which is
     * worse than not sending it at all. Store numbers in full international
     * form and this does the right thing.
     */
    private function normaliseNumber(string $number): string
    {
        return preg_replace('/[^0-9+]/', '', $number) ?? $number;
    }
}
