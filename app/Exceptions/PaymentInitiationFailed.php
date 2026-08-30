<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The gateway would not start a payment session.
 *
 * Bad credentials, the provider being down, or the account not being live yet.
 * Always a problem at our end or theirs — never the patient's fault — so the
 * message they see offers them the way out that still works: pay at the
 * chamber. The detail goes to the log.
 */
class PaymentInitiationFailed extends RuntimeException
{
    public static function forGateway(string $gateway, ?string $reason = null): self
    {
        return new self(sprintf(
            'Could not start a payment with %s%s',
            $gateway,
            $reason ? ': '.$reason : '.',
        ));
    }

    /** What the patient is told. Never the gateway's own error text. */
    public function patientMessage(): string
    {
        return 'Online payment is unavailable at the moment. Your appointment is booked — '
            .'you can pay at the chamber, or try paying online again later.';
    }
}
