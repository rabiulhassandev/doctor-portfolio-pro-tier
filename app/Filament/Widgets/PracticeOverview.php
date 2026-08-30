<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Appointment;
use App\Models\Payment;
use App\Support\Clock;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The four numbers a practice actually wants on opening the panel.
 *
 * Each card links somewhere useful — that is what the lift-on-hover styling in
 * theme.css is for, and a card that moves but does nothing would be a promise
 * the interface fails to keep.
 */
class PracticeOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $today = Clock::today();

        $todayCount = Appointment::query()
            ->onDate($today)
            ->blocking()
            ->count();

        $pendingCount = Appointment::query()
            ->where('status', AppointmentStatus::Pending->value)
            ->where('starts_at', '>=', now())
            ->count();

        $weekCount = Appointment::query()
            ->whereBetween('starts_at', [
                $today->startOfWeek()->utc(),
                $today->endOfWeek()->utc(),
            ])
            ->blocking()
            ->count();

        $monthRevenue = Payment::query()
            ->paid()
            ->whereBetween('paid_at', [
                $today->startOfMonth()->utc(),
                $today->endOfMonth()->utc(),
            ])
            ->sum('amount');

        /*
         | ->icon() is what the theme turns into the large translucent mark
         | bleeding off the right of each card. It is deliberately not the same
         | as ->descriptionIcon(), which stays small beside the supporting line.
         |
         | No ->color() anywhere: every card in this design is the same solid
         | blue, and the theme sets it. Colour-coding four tiles that all mean
         | "here is a number" only makes the one that matters harder to find.
         */
        return [
            Stat::make("Today's appointments", (string) $todayCount)
                ->icon('heroicon-o-calendar-days')
                ->description($todayCount === 0
                    ? 'Nothing booked for today'
                    : $today->format('l, j F'))
                ->url(AppointmentResource::getUrl('index', ['activeTab' => 'today'])),

            Stat::make('Waiting for confirmation', (string) $pendingCount)
                ->icon('heroicon-o-clock')
                ->description($pendingCount === 0
                    ? 'Everything is confirmed'
                    : 'Patients waiting to hear back')
                ->descriptionIcon($pendingCount > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->url(AppointmentResource::getUrl('index', ['activeTab' => 'pending'])),

            Stat::make('This week', (string) $weekCount)
                ->icon('heroicon-o-chart-bar')
                ->description($today->startOfWeek()->format('j M').' – '.$today->endOfWeek()->format('j M'))
                ->url(AppointmentResource::getUrl('index', ['activeTab' => 'upcoming'])),

            Stat::make('Paid this month', static::money($monthRevenue))
                ->icon('heroicon-o-banknotes')
                ->description('Online payments received in '.$today->format('F'))
                ->url(PaymentResource::getUrl('index')),
        ];
    }

    private static function money(float|string|null $amount): string
    {
        return config('booking.payment.currency', 'BDT')
            .' '
            .number_format((float) $amount, 0);
    }
}
