<?php

namespace Database\Factories;

use App\Enums\AvailabilityScope;
use App\Models\AvailabilitySlot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AvailabilitySlot>
 */
class AvailabilitySlotFactory extends Factory
{
    /**
     * A typical evening chamber block: 6pm to 9pm, half-hour appointments.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => AvailabilityScope::Weekly,
            'day_of_week' => 0,   // Sunday
            'specific_date' => null,
            'start_time' => '18:00:00',
            'end_time' => '21:00:00',
            'slot_duration' => 30,
            'max_bookings_per_slot' => 1,
            'replaces_recurring' => true,
            'label' => 'Evening chamber',
            'is_active' => true,
        ];
    }

    /** A rule repeating every week on the given Carbon weekday (0 = Sunday). */
    public function weeklyOn(int $dayOfWeek): static
    {
        return $this->state(fn (): array => [
            'scope' => AvailabilityScope::Weekly,
            'day_of_week' => $dayOfWeek,
            'specific_date' => null,
        ]);
    }

    /**
     * A rule for one date that REPLACES the normal weekly hours.
     * "On the 14th I only sit ten till noon."
     */
    public function onDate(Carbon|string $date): static
    {
        return $this->state(fn (): array => [
            'scope' => AvailabilityScope::Date,
            'day_of_week' => null,
            'specific_date' => $date,
            'replaces_recurring' => true,
        ]);
    }

    /**
     * A rule for one date that is ADDED to the normal weekly hours.
     * "On the 14th I'm also doing an extra evening block."
     */
    public function extraOnDate(Carbon|string $date): static
    {
        return $this->onDate($date)->state(fn (): array => [
            'replaces_recurring' => false,
        ]);
    }

    public function between(string $start, string $end): static
    {
        return $this->state(fn (): array => [
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    public function duration(int $minutes): static
    {
        return $this->state(fn (): array => [
            'slot_duration' => $minutes,
        ]);
    }

    /** A serial-style chamber: several patients booked into the same time. */
    public function capacity(int $seats): static
    {
        return $this->state(fn (): array => [
            'max_bookings_per_slot' => $seats,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
