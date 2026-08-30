<?php

namespace App\Services\Payments\Gateways;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentInitiationFailed;
use App\Models\DoctorProfile;
use App\Models\Payment;
use App\Support\Payments\PaymentRedirect;
use App\Support\Payments\PaymentResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SSLCommerz — the worked example of a real payment gateway.
 *
 * It is the dominant processor in Bangladesh, and covers cards, bKash, Nagad,
 * Rocket and direct bank transfer behind one integration.
 *
 * ===========================================================================
 * THE FLOW
 * ===========================================================================
 *
 *   1. We POST to their init endpoint with the amount and our own tran_id.
 *   2. They answer with a GatewayPageURL; we send the patient's browser there.
 *   3. The patient pays on SSLCommerz's own pages.
 *   4. Their server POSTs the browser back to our success / fail / cancel URL,
 *      and separately posts an IPN from their own machines.
 *   5. >>> We ask their validation API whether any of that was true. <<<
 *
 * ===========================================================================
 * STEP 5 IS NOT OPTIONAL
 * ===========================================================================
 *
 * The callback URL is public and the POST body is entirely attacker-controlled.
 * Anyone can send us a form claiming `status=VALID` for their own booking. The
 * ONLY thing that makes a payment real is asking SSLCommerz directly, with the
 * val_id they issued — and then checking that the amount they report matches
 * what we asked for. Without that amount check a patient pays one taka for a
 * fifteen-hundred-taka consultation and the appointment confirms itself.
 *
 * ===========================================================================
 * SANDBOX
 * ===========================================================================
 *
 * Register free at https://developer.sslcommerz.com/ and put the store id and
 * password in .env as SSLCOMMERZ_STORE_ID / SSLCOMMERZ_STORE_PASSWORD. Leave
 * SSLCOMMERZ_SANDBOX=true until you are ready to take real money.
 */
final class SslCommerzGateway implements PaymentGateway
{
    private const SANDBOX_INIT = 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php';

    private const LIVE_INIT = 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';

    private const SANDBOX_VALIDATE = 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php';

    private const LIVE_VALIDATE = 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';

    /** The two answers that mean the money arrived. */
    private const VALID_STATUSES = ['VALID', 'VALIDATED'];

    /**
     * @param  array<string, mixed>  $config  The `sslcommerz` block of config/booking.php.
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'sslcommerz';
    }

    public function label(): string
    {
        return $this->config['label'] ?? 'Pay online';
    }

    /**
     * No credentials, no option.
     *
     * A fresh install with an empty .env simply does not offer online payment,
     * rather than showing a button that fails when pressed.
     */
    public function isConfigured(): bool
    {
        return filled($this->config['store_id'] ?? null)
            && filled($this->config['store_password'] ?? null);
    }

    // -----------------------------------------------------------------------
    // Starting a payment
    // -----------------------------------------------------------------------

    public function initiate(Payment $payment): PaymentRedirect
    {
        $appointment = $payment->appointment;
        $doctor = DoctorProfile::current();

        try {
            /*
             | An explicit timeout matters more than usual here: buyers run on
             | shared hosting with no queue, so this call happens inside the
             | patient's own request. A hung cURL is a white screen.
             */
            $response = Http::asForm()
                ->timeout(20)
                ->retry(1, 200)
                ->post($this->initUrl(), [
                    'store_id' => $this->config['store_id'],
                    'store_passwd' => $this->config['store_password'],

                    'total_amount' => (string) $payment->amount,
                    'currency' => $payment->currency,
                    'tran_id' => $payment->reference,

                    // Where the browser is sent back to.
                    'success_url' => route('payments.callback', [$this->name(), 'success']),
                    'fail_url' => route('payments.callback', [$this->name(), 'fail']),
                    'cancel_url' => route('payments.callback', [$this->name(), 'cancel']),
                    // And where their servers post, independently of the browser.
                    'ipn_url' => route('payments.ipn', $this->name()),

                    'cus_name' => $appointment->patient_name,
                    'cus_email' => $appointment->patient_email ?: 'noreply@example.com',
                    'cus_phone' => $appointment->patient_phone,
                    'cus_add1' => $doctor->address_line ?: 'N/A',
                    'cus_city' => $doctor->city ?: 'Dhaka',
                    'cus_country' => $doctor->country ?: 'Bangladesh',

                    'product_name' => 'Consultation — '.$appointment->dateLabel(),
                    'product_category' => 'Healthcare',
                    'product_profile' => 'general',

                    // A consultation is not posted anywhere.
                    'shipping_method' => 'NO',
                    'num_of_item' => 1,
                ]);
        } catch (ConnectionException $e) {
            Log::error('SSLCommerz could not be reached.', [
                'payment' => $payment->reference,
                'error' => $e->getMessage(),
            ]);

            throw PaymentInitiationFailed::forGateway('SSLCommerz', 'the gateway could not be reached');
        }

        $body = $response->json() ?? [];

        if (($body['status'] ?? null) !== 'SUCCESS' || blank($body['GatewayPageURL'] ?? null)) {
            // failedreason is theirs; it goes to the log, never to the patient.
            Log::error('SSLCommerz refused to start a payment session.', [
                'payment' => $payment->reference,
                'status' => $body['status'] ?? null,
                'reason' => $body['failedreason'] ?? null,
            ]);

            throw PaymentInitiationFailed::forGateway('SSLCommerz', $body['failedreason'] ?? null);
        }

        $payment->forceFill([
            'gateway_session_key' => $body['sessionkey'] ?? null,
        ])->save();

        return new PaymentRedirect(
            url: $body['GatewayPageURL'],
            gatewayReference: $body['sessionkey'] ?? null,
        );
    }

    // -----------------------------------------------------------------------
    // Believing (or not believing) a callback
    // -----------------------------------------------------------------------

    public function handleCallback(Request $request): PaymentResult
    {
        $reference = (string) $request->input('tran_id');

        $payment = Payment::query()->where('reference', $reference)->first();

        if (! $payment) {
            // Somebody posting a reference we never issued.
            Log::warning('SSLCommerz callback for an unknown transaction.', ['tran_id' => $reference]);

            return PaymentResult::failed($reference, 'Unknown transaction.');
        }

        // The patient pressed cancel on the gateway's page. Not a failure.
        if ($request->input('status') === 'CANCELLED' || $request->routeIs('*cancel*')) {
            return PaymentResult::cancelled($reference, $request->all());
        }

        $valId = (string) $request->input('val_id');

        if (blank($valId)) {
            return PaymentResult::failed($reference, 'The gateway sent no validation id.', $request->all());
        }

        return $this->validateWithGateway($payment, $valId);
    }

    public function verify(Payment $payment): PaymentResult
    {
        $valId = data_get($payment->payload, 'val_id');

        if (blank($valId)) {
            return PaymentResult::failed($payment->reference, 'Nothing to verify against.');
        }

        return $this->validateWithGateway($payment, (string) $valId);
    }

    /**
     * Ask SSLCommerz directly. This is the only authority.
     *
     * Four things must ALL hold before a payment is treated as real:
     *
     *   1. Their API says VALID or VALIDATED.
     *   2. The transaction id is the one we issued.
     *   3. The amount matches to the paisa — compared with bccomp, because
     *      float equality has no place in deciding whether somebody paid.
     *   4. The currency matches.
     *
     * Any one of them failing means the payment did not happen, whatever the
     * browser posted back to us.
     */
    private function validateWithGateway(Payment $payment, string $valId): PaymentResult
    {
        try {
            $response = Http::timeout(20)->get($this->validateUrl(), [
                'val_id' => $valId,
                'store_id' => $this->config['store_id'],
                'store_passwd' => $this->config['store_password'],
                'format' => 'json',
            ]);
        } catch (ConnectionException $e) {
            Log::error('SSLCommerz validation could not be reached.', [
                'payment' => $payment->reference,
                'error' => $e->getMessage(),
            ]);

            // Unverified is not paid. The reconcile command can retry later.
            return PaymentResult::failed($payment->reference, 'The payment could not be verified.');
        }

        $body = $response->json() ?? [];

        $statusIsValid = in_array($body['status'] ?? '', self::VALID_STATUSES, true);
        $referenceMatches = ($body['tran_id'] ?? null) === $payment->reference;
        $amountMatches = bccomp((string) ($body['amount'] ?? '0'), (string) $payment->amount, 2) === 0;
        $currencyMatches = ($body['currency'] ?? null) === $payment->currency;

        if (! ($statusIsValid && $referenceMatches && $amountMatches && $currencyMatches)) {
            Log::warning('An SSLCommerz payment failed verification.', [
                'payment' => $payment->reference,
                'status_ok' => $statusIsValid,
                'reference_ok' => $referenceMatches,
                'amount_ok' => $amountMatches,
                'currency_ok' => $currencyMatches,
                'reported_amount' => $body['amount'] ?? null,
                'expected_amount' => (string) $payment->amount,
            ]);

            return PaymentResult::failed(
                $payment->reference,
                $body['error'] ?? 'The payment could not be verified.',
                $body,
            );
        }

        return new PaymentResult(
            status: PaymentStatus::Paid,
            reference: $payment->reference,
            gatewayTransactionId: $body['bank_tran_id'] ?? null,
            amount: (string) $body['amount'],
            currency: $body['currency'],
            raw: $body,
        );
    }

    private function isSandbox(): bool
    {
        return (bool) ($this->config['sandbox'] ?? true);
    }

    private function initUrl(): string
    {
        return $this->isSandbox() ? self::SANDBOX_INIT : self::LIVE_INIT;
    }

    private function validateUrl(): string
    {
        return $this->isSandbox() ? self::SANDBOX_VALIDATE : self::LIVE_VALIDATE;
    }
}
