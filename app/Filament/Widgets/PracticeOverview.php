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

        return [
            Stat::make("Today's appointments", (string) $todayCount)
                ->description($todayCount === 0
                    ? 'Nothing booked for today'
                    : $today->format('l, j F'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($todayCount > 0 ? 'primary' : 'gray')
                ->url(AppointmentResource::getUrl('index', ['activeTab' => 'today'])),

            Stat::make('Waiting for confirmation', (string) $pendingCount)
                ->description($pendingCount === 0
                    ? 'Everything is confirmed'
                    : 'Patients waiting to hear back')
                ->descriptionIcon($pendingCount > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                // The only number here that is ever a call to action.
                ->color($pendingCount > 0 ? 'warning' : 'success')
                ->url(AppointmentResource::getUrl('index', ['activeTab' => 'pending'])),

            Stat::make('This week', (string) $weekCount)
                ->description($today->startOfWeek()->format('j M').' – '.$today->endOfWeek()->format('j M'))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info')
                ->url(AppointmentResource::getUrl('index', ['activeTab' => 'upcoming'])),

            Stat::make('Paid this month', static::money($monthRevenue))
                ->description('Online payments received in '.$today->format('F'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
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
