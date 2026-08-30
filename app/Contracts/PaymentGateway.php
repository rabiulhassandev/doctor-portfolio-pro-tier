<?php

namespace App\Contracts;

use App\Exceptions\PaymentInitiationFailed;
use App\Models\Payment;
use App\Services\Payments\Gateways\PayAtClinicGateway;
use App\Services\Payments\Gateways\SslCommerzGateway;
use App\Support\Payments\PaymentRedirect;
use App\Support\Payments\PaymentResult;
use Illuminate\Http\Request;

/**
 * A way of taking money.
 *
 * ===========================================================================
 * >>> THIS IS THE PAYMENT INTEGRATION POINT. <<<
 * ===========================================================================
 *
 * SSLCommerz ships as the worked example because the template is written for
 * Bangladesh, but nothing in App\Services\Booking has ever heard of it. To use
 * a different processor:
 *
 *   1. Write a class implementing this interface.
 *   2. Add an entry to `payment.gateways` in config/booking.php with `driver`
 *      pointing at your class. The whole array is passed to your constructor,
 *      so put your credentials in it.
 *   3. Point PAYMENT_GATEWAY at the new key in .env.
 *
 * No booking code changes. There is an architecture test that fails if anybody
 * makes App\Services\Booking depend on a specific gateway.
 *
 * ===========================================================================
 * THE ONE RULE IMPLEMENTATIONS MUST NOT BREAK
 * ===========================================================================
 *
 * handleCallback() may not believe the request. Anyone can post a form to a
 * public callback URL claiming a payment succeeded. Every implementation must
 * confirm the transaction with the provider's own API — including the AMOUNT,
 * or a patient can pay one taka for a fifteen-hundred-taka consultation.
 *
 * @see SslCommerzGateway  The worked example.
 * @see PayAtClinicGateway A gateway that takes no money.
 */
interface PaymentGateway
{
    /** The machine key, matching the array key in config/booking.php. */
    public function name(): string;

    /** What the patient reads on the checkout radio. */
    public function label(): string;

    /**
     * Whether this gateway has everything it needs.
     *
     * False when credentials are missing, and the option is then hidden from
     * checkout rather than failing when pressed — so a fresh install with no
     * keys still takes bookings.
     */
    public function isConfigured(): bool;

    /**
     * Open a session with the provider and say where to send the browser.
     *
     * @throws PaymentInitiationFailed
     */
    public function initiate(Payment $payment): PaymentRedirect;

    /**
     * Turn an inbound callback into a normalised result.
     *
     * MUST verify server-side. See the rule above.
     */
    public function handleCallback(Request $request): PaymentResult;

    /**
     * Ask the provider about a payment again.
     *
     * Used to reconcile one whose callback never arrived — a patient who closed
     * the tab at the wrong moment, or an IPN lost in transit.
     */
    public function verify(Payment $payment): PaymentResult;
}
