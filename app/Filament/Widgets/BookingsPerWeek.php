<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Bookings over the last eight weeks, split by what became of them.
 *
 * Answers the one question a stat card cannot: is the practice getting busier
 * or quieter, and how many of those bookings turn into visits.
 */
class BookingsPerWeek extends ChartWidget
{
    /*
     | Last. It answers a question about the direction of the practice, which
     | matters weekly; the panels above it answer questions about today, which
     | matter now.
     */
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 2;

    protected ?string $heading = 'Bookings over the last eight weeks';

    protected ?string $maxHeight = '280px';

    /** Nothing useful to draw until there is some history. */
    public static function canView(): bool
    {
        return Appointment::query()->exists();
    }

    /**
     * Eight weeks of bookings, in ONE query.
     *
     * This used to run a query per week and, worse, hydrate every appointment
     * in each of them into a model just to count by status — sixteen queries
     * and eight collections of Eloquent objects to draw twenty-four bars.
     *
     * The bucketing stays in PHP rather than becoming a `GROUP BY WEEK(...)`,
     * deliberately. Week-number SQL is different on MySQL, SQLite and Postgres,
     * the test suite runs on SQLite while production runs on MySQL, and a
     * portable index scan over a few hundred rows costs nothing. That is the
     * same reasoning that kept the double-booking guard off a generated column.
     *
     * `toBase()` skips model hydration entirely: two columns of raw values are
     * all the maths needs.
     */
    protected function getData(): array
    {
        $weeks = collect(range(7, 0))
            ->map(fn (int $back) => Clock::today()->subWeeks($back)->startOfWeek());

        $firstWeek = $weeks->first();
        $lastWeek = $weeks->last();

        // Week start (clinic time) => position in the arrays below.
        $indexByWeek = $weeks
            ->mapWithKeys(fn ($week, int $index): array => [$week->toDateString() => $index])
            ->all();

        $completed = array_fill(0, $weeks->count(), 0);
        $cancelled = array_fill(0, $weeks->count(), 0);
        $upcoming = array_fill(0, $weeks->count(), 0);

        $rows = Appointment::query()
            ->whereBetween('starts_at', [$firstWeek->utc(), $lastWeek->endOfWeek()->utc()])
            ->toBase()
            ->get(['starts_at', 'status']);

        foreach ($rows as $row) {
            // Stored UTC, bucketed by the clinic's week — an evening
            // appointment in Dhaka is the previous day in UTC, and bucketing on
            // the raw column would file it under the wrong week.
            $week = Clock::fromStorage(CarbonImmutable::parse($row->starts_at, 'UTC'))
                ->startOfWeek()
                ->toDateString();

            $index = $indexByWeek[$week] ?? null;

            if ($index === null) {
                continue;
            }

            match ($row->status) {
                AppointmentStatus::Completed->value => $completed[$index]++,
                AppointmentStatus::Cancelled->value,
                AppointmentStatus::Rescheduled->value => $cancelled[$index]++,
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Pending->value => $upcoming[$index]++,
                default => null,
            };
        }

        $labels = $weeks->map(fn ($week): string => $week->format('j M'))->all();

        return [
            'datasets' => [
                [
                    'label' => 'Seen',
                    'data' => $completed,
                    'backgroundColor' => '#2f7a5a',
                ],
                [
                    'label' => 'Booked',
                    'data' => $upcoming,
                    'backgroundColor' => '#0f5c86',
                ],
                [
                    'label' => 'Cancelled or moved',
                    'data' => $cancelled,
                    'backgroundColor' => '#94a3b8',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => [
                    'stacked' => true,
                    // Half an appointment is not a thing.
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => ['usePointStyle' => true, 'boxWidth' => 8],
                ],
            ],
        ];
    }
}
