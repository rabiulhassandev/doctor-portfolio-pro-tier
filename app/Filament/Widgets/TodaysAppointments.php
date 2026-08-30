<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Appointments\Actions\AppointmentStatusActions;
use App\Models\Appointment;
use App\Support\Clock;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who is coming in today, in order.
 *
 * The dashboard's main event: a receptionist opening the panel in the morning
 * should see the day's list without navigating anywhere.
 *
 * The confirm button is the same object the appointments table uses, so the two
 * behave identically — same confirmation dialogue, same email.
 */
class TodaysAppointments extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /** Hidden entirely on a quiet day rather than showing an empty panel. */
    public static function canView(): bool
    {
        return Appointment::query()->onDate(Clock::today())->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading("Today's list")
            ->description(Clock::today()->format('l, j F Y'))
            ->query(
                Appointment::query()
                    ->onDate(Clock::today())
                    ->blocking()
                    ->orderBy('starts_at')
                    ->orderBy('seat_no'),
            )
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Time')
                    ->formatStateUsing(fn (Appointment $record): string => $record->startsAtLocal()->format('g:i A'))
                    // Grey out anyone whose slot has already passed, so the eye
                    // lands on who is still to come.
                    ->color(fn (Appointment $record): ?string => $record->starts_at->isPast() ? 'gray' : null)
                    ->weight('semibold'),

                TextColumn::make('patient_name')
                    ->label('Patient')
                    ->description(fn (Appointment $record): string => $record->patient_phone)
                    ->copyable()
                    ->copyableState(fn (Appointment $record): string => $record->patient_phone)
                    ->copyMessage('Phone number copied'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(50)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordActions([
                AppointmentStatusActions::confirm(),
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Appointment $record): string => url('/admin/appointments/'.$record->getRouteKey())),
            ])
            ->paginated([10, 25])
            ->emptyStateHeading('Nothing booked for today');
    }

    /** Widgets get their own query; keep it from being reordered by the table. */
    protected function getTableQuery(): ?Builder
    {
        return null;
    }
}
