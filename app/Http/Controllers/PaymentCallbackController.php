<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Where payment gateways send the patient — and their own servers — back to.
 *
 * ===========================================================================
 * TWO THINGS THAT ARE EASY TO GET WRONG HERE
 * ===========================================================================
 *
 * 1. CSRF is exempted for these routes (see bootstrap/app.php), because the
 *    gateway posts from its own domain with no token. That is only safe because
 *    the gateway driver verifies every payment against the provider's API
 *    before believing a word of the request body.
 *
 * 2. The browser arrives on a cross-site POST, which means a SameSite=Lax
 *    session cookie is NOT sent. The patient looks signed out, and every flash
 *    message vanishes.
 *
 *    The fix is NOT to set SESSION_SAME_SITE=none — that weakens every cookie
 *    on the site to solve one page. Instead this controller is session-free: it
 *    finds the payment by the gateway's own transaction id, processes it, and
 *    then REDIRECTS to a normal GET page. By the time the patient lands there
 *    they are on a same-site navigation and their session is back.
 */
class PaymentCallbackController extends Controller
{
    /** The patient's browser returning from the gateway. */
    public function handle(
        Request $request,
        string $gateway,
        string $outcome,
        PaymentManager $gateways,
        PaymentProcessor $processor,
    ): RedirectResponse {
        $result = $this->process($request, $gateway, $gateways, $processor);

        if ($result === null) {
            return redirect()
                ->route('home')
                ->withErrors(['payment' => 'We could not match that payment. Please contact the chamber.']);
        }

        [$payment, $status] = $result;

        // A plain GET onto a real page the patient can bookmark and refresh.
        $target = route('patient.appointments.show', $payment->appointment);

        return match (true) {
            $status->isSettled() => redirect()->to($target)
                ->with('status', 'Thank you — your payment was received and your appointment is confirmed.'),

            $outcome === 'cancel' => redirect()->to($target)
                ->with('status', 'The payment was cancelled. Your appointment is still booked — you can pay at the chamber.'),

            default => redirect()->to($target)
                ->withErrors(['payment' => 'That payment did not go through. Your appointment is still booked, and you can pay at the chamber or try again.']),
        };
    }

    /**
     * The gateway's own servers, posting independently of the browser.
     *
     * Often arrives before the patient gets back, and sometimes twice.
     * PaymentProcessor is idempotent, which is what makes that safe.
     *
     * Answers with plain JSON: nobody is reading this page.
     */
    public function ipn(
        Request $request,
        string $gateway,
        PaymentManager $gateways,
        PaymentProcessor $processor,
    ): JsonResponse {
        $result = $this->process($request, $gateway, $gateways, $processor);

        // 200 either way. A non-2xx makes most gateways retry for hours, and
        // there is nothing they could usefully retry if we cannot match it.
        return response()->json([
            'received' => true,
            'matched' => $result !== null,
        ]);
    }

    /**
     * Verify and apply, shared by both entry points.
     *
     * @return array{0: Payment, 1: PaymentStatus}|null
     */
    private function process(
        Request $request,
        string $gateway,
        PaymentManager $gateways,
        PaymentProcessor $processor,
    ): ?array {
        try {
            $driver = $gateways->driver($gateway);
        } catch (InvalidArgumentException) {
            Log::warning('A payment callback named an unknown gateway.', ['gateway' => $gateway]);

            return null;
        }

        try {
            // The driver is contractually required to verify this with the
            // provider rather than trusting the request.
            $result = $driver->handleCallback($request);
            $payment = $processor->apply($result);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $payment ? [$payment, $result->status] : null;
    }
}
