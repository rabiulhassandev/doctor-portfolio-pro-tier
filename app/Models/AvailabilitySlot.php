<?php

namespace App\Models;

use App\Enums\AvailabilityScope;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonInterface;
use Database\Factories\AvailabilitySlotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One availability *rule*, not one bookable slot.
 *
 * "Every Sunday, 6pm–9pm, half-hour appointments, two patients each" is a
 * single row here. Turning it into the actual list of times a patient can pick
 * is App\Services\Booking\SlotGenerator's job, and happens per request.
 *
 * @property AvailabilityScope $scope
 * @property int|null $day_of_week
 * @property Carbon|null $specific_date
 * @property string $start_time
 * @property string $end_time
 * @property int $slot_duration
 * @property int $max_bookings_per_slot
 * @property bool $replaces_recurring
 * @property string|null $label
 * @property bool $is_active
 */
class AvailabilitySlot extends Model
{
    /** @use HasFactory<AvailabilitySlotFactory> */
    use HasFactory;

    /**
     * Weekday numbers as Carbon reports them, for the admin dropdown.
     *
     * These are Carbon's own values (0 = Sunday), NOT the display order used by
     * DoctorProfile::DAYS. Keeping them numeric here and letting the form
     * decide the order means the matching query stays a plain integer compare.
     */
    public const WEEKDAYS = [
        6 => 'Saturday',
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
    ];

    /** The shortest appointment the generator will accept, in minutes. */
    public const MIN_DURATION = 5;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scope' => AvailabilityScope::class,
            'day_of_week' => 'integer',
            'specific_date' => 'date',
            'slot_duration' => 'integer',
            'max_bookings_per_slot' => 'integer',
            'replaces_recurring' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Keep the two scope columns honest.
     *
     * A weekly rule has no specific date and a dated rule has no weekday.
     * Clearing the irrelevant one on save means a doctor who fills in a date,
     * changes their mind and switches back to weekly cannot leave a stale value
     * behind that the query would then match on.
     *
     * Pure normalisation, no side effect — the case a model hook is for. The
     * Filament form enforces the same invariant at the point of entry so the
     * doctor sees it; this is the backstop for seeders, tests and tinker.
     */
    protected static function booted(): void
    {
        static::saving(function (self $rule): void {
            if ($rule->scope === AvailabilityScope::Weekly) {
                $rule->specific_date = null;
            } else {
                $rule->day_of_week = null;
            }
        });

        /*
         | AvailabilityService caches the rule set for a few minutes, since it
         | is read on every booking page and changes a handful of times a year.
         | Busting it here means no future caller can forget to — a doctor who
         | edits their hours expects the site to say so immediately, not in
         | five minutes.
         */
        static::saved(fn () => AvailabilityService::forgetCachedRules());
        static::deleted(fn () => AvailabilityService::forgetCachedRules());
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     | Rules that could possibly apply to a given date: the weekly ones for that
     | weekday, plus any written for that exact date.
     |
     | Deciding which of them actually *win* is AvailabilityService's job — see
     | the precedence rules in its doc block. This scope only narrows the query.
     */
    public function scopeForDate(Builder $query, CarbonInterface $date): void
    {
        $query->active()->where(function (Builder $query) use ($date): void {
            $query
                ->where(function (Builder $q) use ($date): void {
                    $q->where('scope', AvailabilityScope::Weekly->value)
                        ->where('day_of_week', $date->dayOfWeek);
                })
                ->orWhere(function (Builder $q) use ($date): void {
                    $q->where('scope', AvailabilityScope::Date->value)
                        ->whereDate('specific_date', $date->toDateString());
                });
        });
    }

    public function isWeekly(): bool
    {
        return $this->scope === AvailabilityScope::Weekly;
    }

    /** "Sunday" or "14 September 2026" — how this rule reads in the admin list. */
    public function whenLabel(): string
    {
        return $this->isWeekly()
            ? (self::WEEKDAYS[$this->day_of_week] ?? 'Unknown day')
            : ($this->specific_date?->format('j F Y') ?? 'No date set');
    }

    /** "6:00 PM – 9:00 PM" */
    public function timeRangeLabel(): string
    {
        return Carbon::parse($this->start_time)->format('g:i A')
            .' – '
            .Carbon::parse($this->end_time)->format('g:i A');
    }

    /**
     * How many appointments this rule creates in one day.
     *
     * Shown in the admin panel so the doctor can sanity-check a rule before
     * saving it — "6pm to 9pm in 10-minute slots" is eighteen appointments an
     * evening, which is worth seeing before patients start booking them.
     */
    public function slotCount(): int
    {
        $minutes = Carbon::parse($this->start_time)->diffInMinutes(Carbon::parse($this->end_time));

        if ($this->slot_duration < self::MIN_DURATION || $minutes <= 0) {
            return 0;
        }

        return (int) floor($minutes / $this->slot_duration);
    }
}
