<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The life of an appointment.
 *
 * This enum owns two things: what each state is *called* everywhere it appears,
 * and which states may legally follow which. Keeping the transition rules here
 * rather than scattered through controllers means there is exactly one answer
 * to "can this be cancelled?", and both the admin panel and the patient
 * dashboard get the same one.
 *
 * Implementing Filament's HasLabel/HasColor/HasIcon contracts means the admin
 * panel renders badges, filters and select options straight from this file —
 * add a case here and it appears everywhere without further edits.
 *
 * The *permission* question ("may this person do it?") is deliberately not here.
 * That belongs to App\Policies\AppointmentPolicy and to the actor checks in
 * App\Services\Booking\AppointmentWorkflow. This enum only answers "is this a
 * legal move at all?".
 */
enum AppointmentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting confirmation',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Rescheduled => 'Moved to a new time',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Completed => 'info',
            self::Cancelled => 'danger',
            self::Rescheduled => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-o-clock',
            self::Confirmed => 'heroicon-o-check-circle',
            self::Completed => 'heroicon-o-clipboard-document-check',
            self::Cancelled => 'heroicon-o-x-circle',
            self::Rescheduled => 'heroicon-o-arrow-path',
        };
    }

    /**
     * The states this one may legally move to.
     *
     * Rescheduled is terminal on purpose. Moving an appointment does not edit
     * the existing row — it creates a *new* appointment at the new time and
     * marks this one Rescheduled, pointing at its replacement through
     * `rescheduled_to_id`. Mutating starts_at in place would throw away the
     * history of what the patient was originally told, and would leave the
     * seat-reservation index describing a booking that no longer exists.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled, self::Rescheduled],
            self::Confirmed => [self::Completed, self::Cancelled, self::Rescheduled],
            self::Completed, self::Cancelled, self::Rescheduled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Nothing further can happen to an appointment in this state. */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Whether an appointment in this state still occupies its seat.
     *
     * A cancelled or rescheduled appointment frees the slot for someone else;
     * a completed one does not, because it genuinely happened and the history
     * has to stay consistent with the availability that was offered.
     */
    public function holdsSeat(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Completed], true);
    }

    /**
     * The states that count against a slot's capacity.
     *
     * Used by the availability query and by the seat-allocation logic. Kept as
     * a single method so the two can never drift apart and start disagreeing
     * about how many places are left.
     *
     * @return array<int, self>
     */
    public static function blocking(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => $status->holdsSeat(),
        ));
    }

    /**
     * The same list as plain strings, for query builders.
     *
     * @return array<int, string>
     */
    public static function blockingValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::blocking());
    }

    /** Shown to patients as "upcoming" rather than as history. */
    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }
}
