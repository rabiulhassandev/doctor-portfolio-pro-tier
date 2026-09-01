<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Appointment;
use App\Models\Payment;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

/**
 * The four numbers a practice actually wants on opening the panel.
 *
 * Each card links somewhere useful — that is what the lift-on-hover styling in
 * theme.css is for, and a card that moves but does nothing would be a promise
 * the interface fails to keep.
 *
 * ---------------------------------------------------------------------------
 * THREE QUERIES, NOT NINE
 * ---------------------------------------------------------------------------
 *
 * Every figure below wants a comparison and a fortnight of history behind it,
 * and asking the database separately for each would be nine round trips for
 * four tiles. Instead two windows are read once — a fortnight of appointments
 * and two months of payments — and everything is counted from those in PHP.
 *
 * The bucketing stays in PHP rather than becoming `GROUP BY DATE(...)` for the
 * same reason as the chart widget: date SQL differs across engines, the tests
 * run on SQLite and production runs on MySQL, and a few hundred rows cost
 * nothing to walk. Portability is worth more here than a micro-optimisation.
 */
class PracticeOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    /**
     * How far back the appointments window reaches.
     *
     * Wide enough to hold this week and last week whatever day it is, which is
     * what the comparisons on the cards need.
     */
    private const WINDOW_DAYS = 14;

    protected function getStats(): array
    {
        $today = Clock::today();

        $appointments = $this->appointmentsByDay($today);
        $revenue = $this->revenueByDay($today);

        return [
            $this->todayStat($today, $appointments),
            $this->pendingStat(),
            $this->weekStat($today, $appointments),
            $this->revenueStat($today, $revenue),
        ];
    }

    // -----------------------------------------------------------------------
    // The cards
    // -----------------------------------------------------------------------

    /** @param  Collection<string, int>  $byDay */
    private function todayStat(CarbonImmutable $today, Collection $byDay): Stat
    {
        $count = $byDay->get($today->toDateString(), 0);
        $yesterday = $byDay->get($today->subDay()->toDateString(), 0);

        return Stat::make("Today's appointments", (string) $count)
            ->icon('heroicon-o-calendar-days')
            ->description($count === 0
                ? 'Nothing booked for today'
                : $today->format('l, j F').$this->comparison($count, $yesterday, 'yesterday'))
            ->descriptionIcon($this->trendIcon($count, $yesterday))
            ->url(AppointmentResource::getUrl('index', ['activeTab' => 'today']));
    }

    /**
     * The one card that changes colour, and only when it has to.
     *
     * The other three report; this one is a job of work — somebody booked and
     * is waiting to hear back. Amber while there is a queue, the same blue as
     * everything else when there is not.
     *
     * Colouring all four would defeat the point: the theme's note about that is
     * right, and this is the exception that proves it rather than a retreat
     * from it. One coloured tile among three blue ones is findable across the
     * room; four coloured tiles are a fruit salad.
     */
    private function pendingStat(): Stat
    {
        /*
         | Upcoming only. A pending appointment whose time has passed is not
         | work waiting to be done, it is a record that needed tidying up weeks
         | ago, and counting it here would leave a number nobody can ever clear.
         */
        $count = Appointment::query()
            ->where('status', AppointmentStatus::Pending->value)
            ->where('starts_at', '>=', now())
            ->count();

        return Stat::make('Waiting for confirmation', (string) $count)
            ->icon('heroicon-o-clock')
            ->description($count === 0
                ? 'Everything is confirmed'
                : $count.' '.str('patient')->plural($count).' waiting to hear back')
            ->descriptionIcon($count > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
            /*
             | A class on the CARD, not ->color().
             |
             | Stat::color() only tints the description text — Filament puts
             | `fi-color-warning` on that one element and nowhere else — and
             | this theme paints the description white anyway, so ->color()
             | here is a no-op that looks like it works. The class is what
             | theme.css can hang a whole amber tile off.
             */
            ->extraAttributes($count > 0 ? ['class' => 'stat-attention'] : [])
            ->url(AppointmentResource::getUrl('index', ['activeTab' => 'pending']));
    }

    /** @param  Collection<string, int>  $byDay */
    private function weekStat(CarbonImmutable $today, Collection $byDay): Stat
    {
        $count = $this->sumBetween($byDay, $today->startOfWeek(), $today->endOfWeek());
        $previous = $this->sumBetween(
            $byDay,
            $today->subWeek()->startOfWeek(),
            $today->subWeek()->endOfWeek(),
        );

        return Stat::make('This week', (string) $count)
            ->icon('heroicon-o-chart-bar')
            ->description(
                $today->startOfWeek()->format('j M').' – '.$today->endOfWeek()->format('j M')
                .$this->comparison($count, $previous, 'last week')
            )
            ->descriptionIcon($this->trendIcon($count, $previous))
            ->url(AppointmentResource::getUrl('index', ['activeTab' => 'upcoming']));
    }

    /** @param  Collection<string, float>  $byDay */
    private function revenueStat(CarbonImmutable $today, Collection $byDay): Stat
    {
        $thisMonth = $this->sumBetween($byDay, $today->startOfMonth(), $today->endOfMonth());

        /*
         | Last month TO THE SAME DAY, not the whole of it.
         |
         | Comparing a full previous month against however much of this one has
         | happened reports a collapse in revenue every single time the month
         | rolls over — on the 1st it said "-100% on last month", which is
         | alarming, meaningless, and exactly the sort of number that teaches
         | people to ignore a dashboard.
         */
        $previousMonth = $today->subMonthNoOverflow();
        $lastMonth = $this->sumBetween(
            $byDay,
            $previousMonth->startOfMonth(),
            $previousMonth->startOfMonth()
                ->addDays($today->day - 1)
                ->min($previousMonth->endOfMonth()),
        );

        return Stat::make('Paid this month', static::money($thisMonth))
            ->icon('heroicon-o-banknotes')
            ->description(
                'Online payments in '.$today->format('F')
                .$this->comparison($thisMonth, $lastMonth, 'the same point last month')
            )
            ->descriptionIcon($this->trendIcon($thisMonth, $lastMonth))
            ->url(PaymentResource::getUrl('index'));
    }

    // -----------------------------------------------------------------------
    // The two windows
    // -----------------------------------------------------------------------

    /**
     * Appointments per clinic day, for the fortnight ending today.
     *
     * Wide enough to cover the sparklines, this week and last week from a
     * single read. Cancelled and rescheduled rows are excluded — a chart of
     * bookings that includes the ones that did not happen flatters the numbers
     * and answers no question anybody has.
     *
     * @return Collection<string, int>
     */
    private function appointmentsByDay(CarbonImmutable $today): Collection
    {
        // Back far enough for the sparkline AND for last week's comparison,
        // whichever reaches further.
        $from = $today->subDays(self::WINDOW_DAYS - 1)->min($today->subWeek()->startOfWeek());

        return $this->bucketByDay(
            Appointment::query()
                ->whereIn('status', AppointmentStatus::blockingValues())
                ->whereBetween('starts_at', [$from->startOfDay()->utc(), $today->endOfDay()->utc()])
                ->toBase()
                ->get(['starts_at']),
            'starts_at',
        );
    }

    /**
     * Money taken per clinic day, over this month and last.
     *
     * @return Collection<string, float>
     */
    private function revenueByDay(CarbonImmutable $today): Collection
    {
        $from = $today->subMonthNoOverflow()->startOfMonth();

        return $this->bucketByDay(
            Payment::query()
                ->paid()
                ->whereBetween('paid_at', [$from->startOfDay()->utc(), $today->endOfMonth()->utc()])
                ->toBase()
                ->get(['paid_at', 'amount']),
            'paid_at',
            'amount',
        );
    }

    /**
     * Sum rows into clinic-local days.
     *
     * The timezone conversion is the point. Rows are stored in UTC and an
     * evening appointment in Dhaka lands on the previous UTC day — bucketing on
     * the raw column would file half the practice's work under yesterday.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<string, float|int>
     */
    private function bucketByDay(Collection $rows, string $dateColumn, ?string $valueColumn = null): Collection
    {
        $buckets = collect();

        foreach ($rows as $row) {
            $day = Clock::fromStorage(CarbonImmutable::parse($row->{$dateColumn}, 'UTC'))->toDateString();
            $value = $valueColumn === null ? 1 : (float) $row->{$valueColumn};

            $buckets[$day] = ($buckets[$day] ?? 0) + $value;
        }

        return $buckets;
    }

    // -----------------------------------------------------------------------
    // Presentation
    // -----------------------------------------------------------------------

    /*
     | NO SPARKLINES ON THESE CARDS, and that was a decision rather than an
     | omission.
     |
     | Stat::chart() was tried and taken out again. Filament's stat chart has no
     | intrinsic size, so Chart.js measures whatever box it finds at init and
     | writes the result to the canvas as inline attributes — repositioning the
     | container in CSS afterwards does not move the drawing, and it rendered as
     | a lump in the corner rather than a line across the tile.
     |
     | Worth noting even if that were fixed: the sparkline wanted the same
     | bottom-right corner as the ghost icon, which is this panel's signature.
     | Two decorative marks fighting over one corner is worse than one, and the
     | "+200% on last week" line already states the trend more precisely than a
     | fourteen-point line at 70% opacity ever could.
     */

    /** @param  Collection<string, float|int>  $byDay */
    private function sumBetween(Collection $byDay, CarbonImmutable $from, CarbonImmutable $to): float|int
    {
        return $byDay
            ->filter(fn ($value, string $day): bool => $day >= $from->toDateString() && $day <= $to->toDateString())
            ->sum();
    }

    /**
     * " · 3 more than last week", or nothing at all.
     *
     * Returns an empty string when there is nothing to compare against. A card
     * that says "no change from last week" on a practice's first Monday is
     * technically true and completely useless.
     */
    private function comparison(float|int $current, float|int $previous, string $label): string
    {
        /*
         | Nothing to compare against means no comparison. An earlier version
         | said "first since yesterday" here, which is both odd English and a
         | claim it cannot actually support — a quiet Monday is not a milestone.
         | Saying nothing is always better than saying something confusing on a
         | card somebody reads in two seconds.
         */
        if ($previous <= 0) {
            return '';
        }

        $change = (int) round((($current - $previous) / $previous) * 100);

        if ($change === 0) {
            return ' · same as '.$label;
        }

        return ' · '.($change > 0 ? '+' : '').$change.'% on '.$label;
    }

    private function trendIcon(float|int $current, float|int $previous): ?string
    {
        return match (true) {
            $previous <= 0 => null,
            $current > $previous => 'heroicon-m-arrow-trending-up',
            $current < $previous => 'heroicon-m-arrow-trending-down',
            default => 'heroicon-m-minus-small',
        };
    }

    private static function money(float|string|null $amount): string
    {
        return config('booking.payment.currency', 'BDT')
            .' '
            .number_format((float) $amount, 0);
    }
}
