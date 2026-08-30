<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Support\Clock;
use Filament\Widgets\ChartWidget;

/**
 * Bookings over the last eight weeks, split by what became of them.
 *
 * Answers the one question a stat card cannot: is the practice getting busier
 * or quieter, and how many of those bookings turn into visits.
 */
class BookingsPerWeek extends ChartWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 2;

    protected ?string $heading = 'Bookings over the last eight weeks';

    protected ?string $maxHeight = '280px';

    /** Nothing useful to draw until there is some history. */
    public static function canView(): bool
    {
        return Appointment::query()->exists();
    }

    protected function getData(): array
    {
        $weeks = collect(range(7, 0))
            ->map(fn (int $back) => Clock::today()->subWeeks($back)->startOfWeek());

        $completed = [];
        $cancelled = [];
        $upcoming = [];
        $labels = [];

        foreach ($weeks as $weekStart) {
            $range = [$weekStart->utc(), $weekStart->endOfWeek()->utc()];

            $counts = Appointment::query()
                ->whereBetween('starts_at', $range)
                ->get(['status'])
                ->countBy(fn (Appointment $a): string => $a->status->value);

            $completed[] = (int) $counts->get(AppointmentStatus::Completed->value, 0);
            $cancelled[] = (int) $counts->get(AppointmentStatus::Cancelled->value, 0)
                + (int) $counts->get(AppointmentStatus::Rescheduled->value, 0);
            $upcoming[] = (int) $counts->get(AppointmentStatus::Confirmed->value, 0)
                + (int) $counts->get(AppointmentStatus::Pending->value, 0);

            $labels[] = $weekStart->format('j M');
        }

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
