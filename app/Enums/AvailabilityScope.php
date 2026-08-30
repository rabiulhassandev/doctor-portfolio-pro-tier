<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether an availability rule repeats every week or applies to one date only.
 *
 * This is technically derivable from which of `day_of_week` / `specific_date`
 * is filled in. It exists as its own column anyway, for three reasons:
 *
 *   1. It turns the admin form into a plain two-option radio that shows and
 *      hides the right fields, rather than asking the doctor to understand
 *      that leaving a box empty is what makes a rule recurring.
 *   2. It makes "both columns empty" impossible to *mean* anything, so a
 *      half-saved row can be rejected instead of silently matching every day.
 *   3. It reads unambiguously to whoever maintains this at two in the morning.
 *
 * One redundant column is a fair price for all three.
 */
enum AvailabilityScope: string implements HasColor, HasLabel
{
    /** Repeats every week on the same weekday. The normal case. */
    case Weekly = 'weekly';

    /** Applies to one calendar date only, and overrides the weekly rules. */
    case Date = 'date';

    public function getLabel(): string
    {
        return match ($this) {
            self::Weekly => 'Every week',
            self::Date => 'One specific date',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Weekly => 'info',
            self::Date => 'warning',
        };
    }

    /** Helper text for the admin form, kept beside the labels it belongs with. */
    public function description(): string
    {
        return match ($this) {
            self::Weekly => 'Your normal schedule — for example, every Sunday evening.',
            self::Date => 'A one-off change for a single day, which replaces your normal hours for that day.',
        };
    }
}
