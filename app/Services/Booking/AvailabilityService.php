<?php

namespace App\Services\Booking;

use App\Enums\AvailabilityScope;
use App\Models\Appointment;
use App\Models\AvailabilityBlackout;
use App\Models\AvailabilitySlot;
use App\Models\DoctorProfile;
use App\Support\Clock;
use App\Support\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Works out which appointment times a patient may actually book.
 *
 * ===========================================================================
 * THE PRECEDENCE RULES, STATED ONCE
 * ===========================================================================
 *
 * For any given date, in this order:
 *
 *   1. A BLACKOUT covering the date wins over everything. Zero slots. No
 *      exceptions, no "but the date-specific rule said…". Having one rule that
 *      always wins is what makes "I'm away next week" a safe thing for a
 *      doctor to enter at eleven at night.
 *
 *   2. A DATE-SPECIFIC rule with replaces_recurring = true discards the weekly
 *      rules for that weekday entirely. This is the common case: "on the 14th
 *      I only sit ten till noon".
 *
 *   3. A DATE-SPECIFIC rule with replaces_recurring = false is added to the
 *      weekly ones. "On the 14th I'm also doing an extra evening block."
 *
 *   4. Otherwise, the active weekly rules for that weekday.
 *
 * Then three subtractions, always:
 *   - times outside the booking horizon
 *   - places already taken by live bookings
 *   - times that have passed, or are too soon to give the chamber notice
 *
 * ===========================================================================
 * WHAT IS AND IS NOT CACHED
 * ===========================================================================
 *
 * The RULES are cached — they change a few times a year. The RESULT never is.
 * Booked counts change by the second, and a stale availability grid is
 * precisely how two patients end up holding the same appointment.
 */
final class AvailabilityService
{
    /** Rules change rarely; five minutes is plenty and is busted on save anyway. */
    private const RULES_CACHE_TTL = 300;

    private const RULES_CACHE_KEY = 'availability.rules';

    public function __construct(private readonly SlotGenerator $generator) {}

    /**
     * Bookable slots for one date, in time order.
     *
     * Costs three queries: the blackouts, the rules (usually cached), and the
     * booked counts.
     *
     * @return Collection<int, Slot>
     */
    public function slotsForDate(CarbonImmutable $date): Collection
    {
        $date = $date->setTimezone(Clock::timezone())->startOfDay();

        if (! $this->isWithinHorizon($date) || $this->blackoutFor($date) !== null) {
            return collect();
        }

        $ranges = $this->generator->expand($date, $this->rulesFor($date));

        if ($ranges->isEmpty()) {
            return collect();
        }

        return $this->toSlots($ranges, $this->bookedCounts($date, $date))
            ->filter(fn (Slot $slot): bool => $slot->isBookable())
            ->values();
    }

    /**
     * Bookable slots across a range of dates, keyed 'Y-m-d'.
     *
     * Still three queries for the whole span, not three per day. Building a
     * month calendar day by day would be ninety queries, which on the shared
     * hosting most buyers run is the difference between a page that loads and
     * one that times out.
     *
     * @return Collection<string, Collection<int, Slot>>
     */
    public function slotsForRange(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $from = $from->setTimezone(Clock::timezone())->startOfDay();
        $to = $to->setTimezone(Clock::timezone())->startOfDay();

        if ($to->lessThan($from)) {
            return collect();
        }

        // All three queries, once.
        $blackouts = AvailabilityBlackout::query()->overlapping($from, $to)->get();
        $rules = $this->cachedRules();
        $booked = $this->bookedCounts($from, $to);

        $days = collect();

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            if (! $this->isWithinHorizon($date)) {
                $days[$date->toDateString()] = collect();

                continue;
            }

            /*
             | Compared as date STRINGS, not as Carbon instances.
             |
             | $date is midnight in the clinic's timezone; the blackout columns
             | are cast to dates and come back as midnight in the app's
             | timezone, which is UTC. Comparing the two directly is out by the
             | offset — for Asia/Dhaka, clinic midnight is 6pm UTC the previous
             | day, so a blacked-out date fell just outside its own range and
             | the calendar happily offered it.
             |
             | A calendar day is a label, not an instant. Compare the labels.
             | (AvailabilityBlackout::scopeCovering does the same thing in SQL,
             | which is why the single-date path was never affected.)
             */
            $day = $date->toDateString();

            $isBlackedOut = $blackouts->contains(
                fn (AvailabilityBlackout $blackout): bool => $day >= $blackout->starts_on->toDateString()
                    && $day <= $blackout->ends_on->toDateString(),
            );

            if ($isBlackedOut) {
                $days[$date->toDateString()] = collect();

                continue;
            }

            $ranges = $this->generator->expand($date, $this->applyPrecedence($rules, $date));

            $days[$date->toDateString()] = $this->toSlots($ranges, $booked)
                ->filter(fn (Slot $slot): bool => $slot->isBookable())
                ->values();
        }

        return $days;
    }

    /**
     * The dates inside the horizon that have at least one place left.
     *
     * Drives the date picker, so a patient is never offered a day that turns
     * out to be empty when they tap it.
     *
     * @return Collection<int, CarbonImmutable>
     */
    public function bookableDates(): Collection
    {
        return $this->slotsForRange($this->horizonStart(), $this->horizonEnd())
            ->filter(fn (Collection $slots): bool => $slots->isNotEmpty())
            ->keys()
            ->map(fn (string $date): CarbonImmutable => Clock::parse($date))
            ->values();
    }

    /**
     * The authoritative check, used at the moment of booking.
     *
     * The form posts a time and nothing else, and this is what turns that time
     * back into a slot with a real capacity and duration. Returns null when the
     * time is not bookable — because it never existed, because the day is
     * blacked out, because it is full, or because it has passed.
     *
     * BookingService calls this inside its transaction. Never trust a posted
     * duration or price; re-derive both here.
     */
    public function findBookableSlot(CarbonImmutable $startsAt): ?Slot
    {
        $startsAt = $startsAt->setTimezone(Clock::timezone());

        return $this->slotsForDate($startsAt->startOfDay())
            ->first(fn (Slot $slot): bool => $slot->startsAt->equalTo($startsAt));
    }

    // -----------------------------------------------------------------------
    // Horizon
    // -----------------------------------------------------------------------

    /** Today. Patients cannot book into the past. */
    public function horizonStart(): CarbonImmutable
    {
        return Clock::today();
    }

    /**
     * The last date that may be booked.
     *
     * The doctor's own setting wins over the developer default, and this is the
     * only place the two are reconciled — a second copy of this rule anywhere
     * else is how a calendar starts disagreeing with the form it submits to.
     */
    public function horizonEnd(): CarbonImmutable
    {
        $days = DoctorProfile::current()->booking_horizon_days
            ?: (int) config('booking.horizon_days', 30);

        return Clock::today()->addDays(max(1, $days))->endOfDay();
    }

    public function isWithinHorizon(CarbonImmutable $date): bool
    {
        return $date->betweenIncluded($this->horizonStart(), $this->horizonEnd());
    }

    /** The blackout covering this date, if any — so the UI can say why. */
    public function blackoutFor(CarbonImmutable $date): ?AvailabilityBlackout
    {
        return AvailabilityBlackout::query()
            ->covering($date)
            ->first();
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * The rules that win for one date. See the precedence rules at the top.
     *
     * @return Collection<int, AvailabilitySlot>
     */
    private function rulesFor(CarbonImmutable $date): Collection
    {
        return $this->applyPrecedence($this->cachedRules(), $date);
    }

    /**
     * Apply the precedence rules to an already-loaded set.
     *
     * Separate from rulesFor() so slotsForRange() can reuse one query's worth
     * of rules across every date in the span.
     *
     * @param  Collection<int, AvailabilitySlot>  $rules
     * @return Collection<int, AvailabilitySlot>
     */
    private function applyPrecedence(Collection $rules, CarbonImmutable $date): Collection
    {
        $dated = $rules->filter(
            fn (AvailabilitySlot $rule): bool => $rule->scope === AvailabilityScope::Date
                && $rule->specific_date?->isSameDay($date),
        );

        $weekly = $rules->filter(
            fn (AvailabilitySlot $rule): bool => $rule->scope === AvailabilityScope::Weekly
                && $rule->day_of_week === $date->dayOfWeek,
        );

        if ($dated->isEmpty()) {
            return $weekly->values();
        }

        // One replacing rule is enough to set aside the whole normal day. A
        // doctor writing "on the 14th I sit ten till noon" means instead of,
        // not as well as — otherwise their evening would still be offered.
        $replaces = $dated->contains(fn (AvailabilitySlot $rule): bool => $rule->replaces_recurring);

        return $replaces
            ? $dated->values()
            : $weekly->concat($dated)->values();
    }

    /**
     * Every active rule, cached.
     *
     * A single-doctor practice has a handful of these, so loading them all and
     * filtering in PHP is cheaper than a query per date — and it is what makes
     * the whole-month view affordable.
     *
     * ARRAYS are cached, not model instances, and then hydrated back on read.
     * Caching Eloquent models means serialising them, and any cache store that
     * actually serialises — `database`, `file`, `redis`, i.e. everything a
     * buyer will really run — hands back `__PHP_Incomplete_Class` if the class
     * cannot be resolved at unserialise time. Rows are plain data and always
     * survive the round trip.
     *
     * @return Collection<int, AvailabilitySlot>
     */
    private function cachedRules(): Collection
    {
        $rows = Cache::remember(
            self::RULES_CACHE_KEY,
            self::RULES_CACHE_TTL,
            fn (): array => AvailabilitySlot::query()->active()->get()->map->getRawOriginal()->all(),
        );

        return AvailabilitySlot::hydrate($rows);
    }

    /** Called from the model whenever a rule changes. */
    public static function forgetCachedRules(): void
    {
        Cache::forget(self::RULES_CACHE_KEY);
    }

    /**
     * How many places are already taken, keyed by 'Y-m-d H:i' in clinic time.
     *
     * Counts only bookings that hold their seat, and ignores those whose
     * payment hold has lapsed — that seat is about to be reclaimed and should
     * be offered to whoever is looking at the page now.
     *
     * @return Collection<string, int>
     */
    private function bookedCounts(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Appointment::query()
            ->whereBetween('starts_at', [
                $from->startOfDay()->utc(),
                $to->endOfDay()->utc(),
            ])
            ->blocking()
            ->notExpiredHold()
            ->get(['starts_at'])
            ->groupBy(fn (Appointment $appointment): string => Clock::fromStorage($appointment->starts_at)->format('Y-m-d H:i'))
            ->map(fn (Collection $group): int => $group->count());
    }

    /**
     * Turn raw time ranges into Slots, subtracting bookings and past times.
     *
     * The minimum-notice cut handles "today" for free: there is no isToday()
     * special case anywhere, because a slot that has already passed and a slot
     * three minutes away fail the same comparison.
     *
     * @param  Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, capacity: int, rule_id: int}>  $ranges
     * @param  Collection<string, int>  $booked
     * @return Collection<int, Slot>
     */
    private function toSlots(Collection $ranges, Collection $booked): Collection
    {
        /*
         | "At least this much notice" includes the boundary: with a two-hour
         | rule, a slot exactly two hours away is acceptable. Hence >= rather
         | than >. Setting min_notice_minutes to 0 therefore means literally no
         | minimum, which is what a buyer choosing 0 is asking for.
         */
        $earliest = Clock::now()->addMinutes((int) config('booking.min_notice_minutes', 0));

        return $ranges
            ->filter(fn (array $range): bool => $range['starts_at']->greaterThanOrEqualTo($earliest))
            ->map(fn (array $range): Slot => new Slot(
                startsAt: $range['starts_at'],
                endsAt: $range['ends_at'],
                capacity: $range['capacity'],
                booked: (int) $booked->get($range['starts_at']->format('Y-m-d H:i'), 0),
                availabilitySlotId: $range['rule_id'],
            ))
            ->values();
    }
}
