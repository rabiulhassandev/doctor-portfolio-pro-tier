<?php

namespace App\Support\Payments;

/**
 * Where to send the patient's browser to pay.
 *
 * Two shapes, because gateways differ: most hand back a URL to visit, some
 * require a form POST with signed fields. Both are described here so
 * PaymentController has one code path rather than a branch per provider.
 */
final readonly class PaymentRedirect
{
    /**
     * @param  string  $url  Where to send them.
     * @param  string  $method  'GET' or 'POST'.
     * @param  array<string, string>  $fields  Hidden inputs, when method is POST.
     * @param  string|null  $gatewayReference  A session token to record, if the provider issued one.
     */
    public function __construct(
        public string $url,
        public string $method = 'GET',
        public array $fields = [],
        public ?string $gatewayReference = null,
    ) {}

    public function isPost(): bool
    {
        return strtoupper($this->method) === 'POST';
    }

    /**
     * A gateway that takes no money at all — "pay at the chamber".
     *
     * Still a redirect, to the confirmation page, so the caller never has to
     * ask whether a real payment is happening.
     */
    public static function toConfirmation(string $url): self
    {
        return new self($url);
    }
}
