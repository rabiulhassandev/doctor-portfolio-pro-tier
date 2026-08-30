<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Support\Clock;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * The appointment list, in tabs that match how a chamber actually works.
 *
 * It opens on "Today" rather than on everything, because the first question
 * anyone opening this screen has is who is coming in this evening.
 */
class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    public function getTabs(): array
    {
        return [
            'today' => Tab::make('Today')
                ->icon('heroicon-o-sun')
                ->modifyQueryUsing(fn (Builder $query) => $query->onDate(Clock::today())->reorder('starts_at'))
                ->badge(Appointment::query()->onDate(Clock::today())->count()),

            'pending' => Tab::make('Waiting for confirmation')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('status', AppointmentStatus::Pending->value)
                    ->where('starts_at', '>=', now())
                    ->reorder('starts_at'))
                ->badge(fn (): ?int => Appointment::query()
                    ->where('status', AppointmentStatus::Pending->value)
                    ->where('starts_at', '>=', now())
                    ->count() ?: null)
                ->badgeColor('warning'),

            'upcoming' => Tab::make('Still to come')
                ->icon('heroicon-o-calendar-days')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('starts_at', '>=', now())
                    ->whereIn('status', [
                        AppointmentStatus::Pending->value,
                        AppointmentStatus::Confirmed->value,
                    ])
                    ->reorder('starts_at')),

            'past' => Tab::make('Past')
                ->icon('heroicon-o-archive-box')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('starts_at', '<', now())
                    ->reorder('starts_at', 'desc')),

            'cancelled' => Tab::make('Cancelled')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', [
                        AppointmentStatus::Cancelled->value,
                        AppointmentStatus::Rescheduled->value,
                    ])
                    ->reorder('starts_at', 'desc')),

            'all' => Tab::make('All')
                ->modifyQueryUsing(fn (Builder $query) => $query->reorder('starts_at', 'desc')),
        ];
    }

    /** Staff arrive here wanting today's list, not the whole history. */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'today';
    }
}
