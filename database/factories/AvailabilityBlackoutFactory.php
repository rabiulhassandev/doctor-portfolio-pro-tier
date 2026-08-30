<?php

namespace Database\Factories;

use App\Models\AvailabilityBlackout;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityBlackout>
 */
class AvailabilityBlackoutFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = Clock::today()->addDays(10);

        return [
            'starts_on' => $starts,
            'ends_on' => $starts->addDays(2),
            'reason' => 'Away at a conference',
        ];
    }

    /** A single day off. */
    public function on(CarbonImmutable|string $date): static
    {
        return $this->state(fn (): array => [
            'starts_on' => $date,
            'ends_on' => $date,
        ]);
    }

    public function between(CarbonImmutable|string $from, CarbonImmutable|string $to): static
    {
        return $this->state(fn (): array => [
            'starts_on' => $from,
            'ends_on' => $to,
        ]);
    }
}
