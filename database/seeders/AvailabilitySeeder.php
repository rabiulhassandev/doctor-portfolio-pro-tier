<?php

namespace Database\Seeders;

use App\Enums\AvailabilityScope;
use App\Models\AvailabilityBlackout;
use App\Models\AvailabilitySlot;
use App\Support\Clock;
use Illuminate\Database\Seeder;

/**
 * A realistic chamber schedule, so booking is testable the moment you install.
 *
 * Five evenings and one morning a week, Friday off. Thirty-minute appointments,
 * one patient each — a serial-style chamber would raise
 * max_bookings_per_slot instead.
 *
 * Note the deliberate difference from the *published* opening hours in
 * DoctorProfileSeeder: the chamber opens at six, but booked appointments start
 * at six thirty. Walk-ins take the first half hour. Two different things, two
 * different tables — which is exactly why they are separate.
 */
class AvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        /*
         | Carbon weekday numbers: 0 = Sunday … 6 = Saturday.
         | The working week here runs Saturday to Thursday.
         */
        $evenings = [6, 0, 1, 2, 3];   // Saturday through Wednesday

        foreach ($evenings as $day) {
            AvailabilitySlot::query()->updateOrCreate(
                [
                    'scope' => AvailabilityScope::Weekly,
                    'day_of_week' => $day,
                    'start_time' => '18:30:00',
                ],
                [
                    'end_time' => '21:00:00',
                    'slot_duration' => 30,
                    'max_bookings_per_slot' => 1,
                    'label' => 'Evening chamber',
                    'is_active' => true,
                ],
            );
        }

        // Thursday morning, shorter appointments — mostly follow-ups.
        AvailabilitySlot::query()->updateOrCreate(
            [
                'scope' => AvailabilityScope::Weekly,
                'day_of_week' => 4,
                'start_time' => '10:00:00',
            ],
            [
                'end_time' => '13:00:00',
                'slot_duration' => 20,
                'max_bookings_per_slot' => 1,
                'label' => 'Thursday morning follow-ups',
                'is_active' => true,
            ],
        );

        /*
         | A blackout a fortnight out, so the demo shows a closed day on the
         | booking calendar without a developer having to create one.
         */
        $away = Clock::today()->addDays(14);

        AvailabilityBlackout::query()->updateOrCreate(
            ['starts_on' => $away->toDateString()],
            [
                'ends_on' => $away->addDay()->toDateString(),
                'reason' => 'Away at a conference',
            ],
        );
    }
}
