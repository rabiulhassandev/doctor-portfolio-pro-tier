<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * What happened to a payment attempt.
 *
 * Gateways all use their own vocabulary — SSLCommerz alone answers VALID,
 * VALIDATED, FAILED, CANCELLED and PENDING. Each gateway driver translates its
 * provider's words into one of these five cases, so the rest of the application
 * never has to know which processor took the money.
 */
enum PaymentStatus: string implements HasColor, HasIcon, HasLabel
{
    /** Created, but the patient has not finished at the gateway yet. */
    case Pending = 'pending';

    /** Money confirmed received. The only state that confirms an appointment. */
    case Paid = 'paid';

    case Failed = 'failed';

    /** The patient backed out at the gateway. Not an error — just a decision. */
    case Cancelled = 'cancelled';

    case Refunded = 'refunded';

    /**
     * The patient chose to pay in person, so there is nothing to collect
     * online. Kept distinct from Pending: an unfinished card payment needs
     * chasing, whereas this is simply how the clinic gets paid that day.
     */
    case DueAtClinic = 'due_at_clinic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
            self::DueAtClinic => 'Pay at the chamber',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed, self::Cancelled => 'danger',
            self::Refunded => 'info',
            self::DueAtClinic => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Paid => 'heroicon-o-check-badge',
            self::Failed => 'heroicon-o-exclamation-triangle',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Refunded => 'heroicon-o-arrow-uturn-left',
            self::DueAtClinic => 'heroicon-o-banknotes',
        };
    }

    /** Whether the clinic has actually been paid. */
    public function isSettled(): bool
    {
        return $this === self::Paid;
    }

    /** Whether this attempt is over, one way or the other. */
    public function isFinished(): bool
    {
        return $this !== self::Pending;
    }
}
