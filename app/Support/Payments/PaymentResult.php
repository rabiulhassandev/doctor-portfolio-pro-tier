<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;

/**
 * What a gateway said about a payment, translated into our own vocabulary.
 *
 * Every provider has its own words — SSLCommerz alone answers VALID, VALIDATED,
 * FAILED, CANCELLED and PENDING. Each driver maps its provider's language into
 * this one shape, which is what lets PaymentProcessor stay gateway-agnostic.
 *
 * Note that `amount` is a numeric STRING, never a float. It is compared with
 * bccomp() against what we expected, and that comparison is the only thing
 * standing between the clinic and a patient paying one taka for a
 * fifteen-hundred-taka consultation. Float equality has no business anywhere
 * near it.
 */
final readonly class PaymentResult
{
    /**
     * @param  string  $reference  Our own transaction id, from Payment::$reference.
     * @param  array<string, mixed>  $raw  The verified provider response, kept for disputes.
     */
    public function __construct(
        public PaymentStatus $status,
        public string $reference,
        public ?string $gatewayTransactionId = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    /** A payment the provider rejected, or that failed verification here. */
    public static function failed(string $reference, ?string $message = null, array $raw = []): self
    {
        return new self(
            status: PaymentStatus::Failed,
            reference: $reference,
            message: $message,
            raw: $raw,
        );
    }

    /** The patient backed out at the gateway. Not an error — a decision. */
    public static function cancelled(string $reference, array $raw = []): self
    {
        return new self(
            status: PaymentStatus::Cancelled,
            reference: $reference,
            message: 'The payment was cancelled.',
            raw: $raw,
        );
    }
}
