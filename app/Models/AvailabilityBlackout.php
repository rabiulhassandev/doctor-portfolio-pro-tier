<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\AvailabilityBlackoutFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A period the doctor is not seeing anyone.
 *
 * A blackout beats every availability rule. See AvailabilityService for where
 * that precedence is applied.
 *
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property string|null $reason
 */
class AvailabilityBlackout extends Model
{
    /** @use HasFactory<AvailabilityBlackoutFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * Tolerate a range entered backwards.
     *
     * Someone will eventually type the end date into the start box. Swapping
     * silently is right here: the doctor's intent ("I am away between these two
     * dates") is unambiguous, and the alternative is a validation error on a
     * screen they are using to say they will be out of the country.
     */
    protected static function booted(): void
    {
        static::saving(function (self $blackout): void {
            if ($blackout->starts_on && $blackout->ends_on && $blackout->starts_on->gt($blackout->ends_on)) {
                [$blackout->starts_on, $blackout->ends_on] = [$blackout->ends_on, $blackout->starts_on];
            }
        });
    }

    /*
     | These take CarbonInterface rather than a concrete class on purpose: the
     | booking layer works in CarbonImmutable (see App\Support\Clock) while
     | Eloquent hands back mutable Carbon, and both must be accepted.
     */

    /** Blackouts covering the given date. The range is inclusive at both ends. */
    public function scopeCovering(Builder $query, CarbonInterface $date): void
    {
        $query->whereDate('starts_on', '<=', $date->toDateString())
            ->whereDate('ends_on', '>=', $date->toDateString());
    }

    /** Blackouts overlapping a range, for drawing the booking calendar. */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereDate('starts_on', '<=', $to->toDateString())
            ->whereDate('ends_on', '>=', $from->toDateString());
    }

    /** Blackouts that have not finished yet — the only ones worth showing. */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereDate('ends_on', '>=', now()->toDateString());
    }

    public function isSingleDay(): bool
    {
        return $this->starts_on->isSameDay($this->ends_on);
    }

    /** "14 September 2026" or "10 – 20 September 2026". */
    public function dateRangeLabel(): string
    {
        if ($this->isSingleDay()) {
            return $this->starts_on->format('j F Y');
        }

        // Drop the repeated month when both ends fall in the same one.
        $from = $this->starts_on->isSameMonth($this->ends_on)
            ? $this->starts_on->format('j')
            : $this->starts_on->format('j F');

        return $from.' – '.$this->ends_on->format('j F Y');
    }

    /** What a patient is told on the booking calendar. */
    public function publicReason(): string
    {
        return $this->reason ?: 'The chamber is closed on this date';
    }
}
