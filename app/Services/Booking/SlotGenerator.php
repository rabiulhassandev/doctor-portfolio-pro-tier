<?php

namespace App\Services\Booking;

use App\Models\AvailabilitySlot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Expands availability rules into time ranges.
 *
 * Pure: no database, no clock, no configuration. Give it a date and a set of
 * rules and it gives back the times those rules describe. That makes it
 * trivially testable, and it keeps the fiddly arithmetic in one place away
 * from the querying and the precedence logic, which live in AvailabilityService.
 *
 * It knows nothing about bookings, blackouts or whether a time has already
 * passed. Those are subtractions applied afterwards.
 */
final class SlotGenerator
{
    /**
     * A hard ceiling on how many slots one rule may produce.
     *
     * Nothing legitimate comes close — a twelve-hour day in five-minute
     * appointments is 144. This exists so that a rule saved with a nonsensical
     * duration cannot spin the loop long enough to hang the request.
     */
    private const MAX_SLOTS_PER_RULE = 500;

    /**
     * Turn rules into time ranges for one date.
     *
     * Ranges that begin at the same moment under different rules are merged,
     * keeping the largest capacity. Overlapping rules are a data-entry mistake
     * rather than an intention, and a patient should never be shown six o'clock
     * twice.
     *
     * @param  Collection<int, AvailabilitySlot>  $rules
     * @return Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, capacity: int, rule_id: int}>
     *                                                                                                                   Ordered by start time.
     */
    public function expand(CarbonImmutable $date, Collection $rules): Collection
    {
        $ranges = [];

        foreach ($rules as $rule) {
            foreach ($this->expandRule($date, $rule) as $range) {
                $key = $range['starts_at']->format('H:i');

                // First rule to claim a time wins its id; capacity takes the
                // most generous reading, since both rules genuinely offered it.
                if (isset($ranges[$key])) {
                    $ranges[$key]['capacity'] = max($ranges[$key]['capacity'], $range['capacity']);

                    continue;
                }

                $ranges[$key] = $range;
            }
        }

        return collect($ranges)
            ->sortBy(fn (array $range): string => $range['starts_at']->format('H:i'))
            ->values();
    }

    /**
     * Walk one rule from its start time to its end in fixed steps.
     *
     * @return array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, capacity: int, rule_id: int}>
     */
    private function expandRule(CarbonImmutable $date, AvailabilitySlot $rule): array
    {
        // A rule saved with a nonsense duration produces nothing rather than
        // an infinite loop or a division by zero.
        if ($rule->slot_duration < AvailabilitySlot::MIN_DURATION) {
            return [];
        }

        $day = $date->startOfDay();
        $cursor = $this->applyTime($day, $rule->start_time);
        $end = $this->applyTime($day, $rule->end_time);

        /*
         | An end time of midnight means the end of *this* day, not the start
         | of it. Without this a "9pm to midnight" rule produces nothing at all,
         | which looks like the rule was ignored.
         */
        if ($end->lessThanOrEqualTo($cursor)) {
            $end = $end->addDay();
        }

        $ranges = [];

        while ($ranges === [] || count($ranges) < self::MAX_SLOTS_PER_RULE) {
            $slotEnd = $cursor->addMinutes($rule->slot_duration);

            // Only whole appointments. A rule ending at 9pm with 45-minute
            // slots stops at 8:15 rather than offering one that runs over.
            if ($slotEnd->greaterThan($end)) {
                break;
            }

            $ranges[] = [
                'starts_at' => $cursor,
                'ends_at' => $slotEnd,
                'capacity' => max(1, $rule->max_bookings_per_slot),
                'rule_id' => (int) $rule->getKey(),
            ];

            $cursor = $slotEnd;
        }

        return $ranges;
    }

    /**
     * Put a stored time-of-day onto a given date, keeping the date's timezone.
     *
     * The column comes back as "18:00:00" (MySQL) or occasionally as a full
     * timestamp depending on the driver, so only the clock part is used.
     */
    private function applyTime(CarbonImmutable $day, string $time): CarbonImmutable
    {
        [$hour, $minute, $second] = array_pad(
            array_map('intval', explode(':', substr($time, -8))),
            3,
            0,
        );

        return $day->setTime($hour, $minute, $second);
    }
}
