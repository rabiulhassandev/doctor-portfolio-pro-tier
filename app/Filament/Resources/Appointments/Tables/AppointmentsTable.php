<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Appointments\Actions\AppointmentStatusActions;
use App\Models\Appointment;
use App\Support\Clock;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The appointment book.
 *
 * Written for a receptionist with a phone in one hand: the time and the
 * patient's number are the two things that matter, so they lead. Everything
 * else is secondary text under them rather than another column to scan across.
 */
class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'asc')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('When')
                    ->sortable()
                    ->searchable(false)
                    // Filament renders in the clinic timezone because
                    // AppServiceProvider sets FilamentTimezone.
                    ->formatStateUsing(fn (Appointment $record): string => $record->startsAtLocal()->format('g:i A'))
                    ->description(fn (Appointment $record): string => $record->startsAtLocal()->format('D, j M Y'))
                    // Draw the eye to anything happening today.
                    ->color(fn (Appointment $record): ?string => $record->startsAtLocal()->isToday() ? 'primary' : null)
                    ->weight('semibold'),

                TextColumn::make('patient_name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Appointment $record): string => $record->patient_phone)
                    // Tapping the number is the most common next action.
                    ->copyable()
                    ->copyableState(fn (Appointment $record): string => $record->patient_phone)
                    ->copyMessage('Phone number copied'),

                TextColumn::make('reference')
                    ->label('Ref')
                    ->searchable()
                    ->toggleable()
                    ->copyable()
                    ->copyMessage('Booking number copied')
                    ->size('sm'),

                TextColumn::make('seat_no')
                    ->label('No.')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter()
                    // Only meaningful in a serial-system chamber where several
                    // patients share a time.
                    ->tooltip('Position within the slot, for chambers that see several patients at once'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('fee_amount')
                    ->label('Fee')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (Appointment $record): string => $record->formattedFee() ?? '—'),

                TextColumn::make('created_at')
                    ->label('Booked')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AppointmentStatus::class)
                    ->multiple(),

                SelectFilter::make('payment_status')
                    ->label('Payment')
                    ->options(PaymentStatus::class)
                    ->multiple(),

                Filter::make('upcoming')
                    ->label('Still to come')
                    ->query(fn (Builder $query): Builder => $query->where('starts_at', '>=', now()))
                    ->default(),

                Filter::make('today')
                    ->label('Today only')
                    ->query(fn (Builder $query): Builder => $query->onDate(Clock::today())),

                Filter::make('needs_payment')
                    ->label('Fee not yet paid')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $query): void {
                        $query->whereNull('payment_status')
                            ->orWhere('payment_status', '!=', PaymentStatus::Paid->value);
                    })),
            ])
            ->recordActions([
                // The two most common answers, as buttons; the rest folded away.
                AppointmentStatusActions::confirm(),
                ActionGroup::make([
                    ViewAction::make(),
                    AppointmentStatusActions::complete(),
                    AppointmentStatusActions::reschedule(),
                    AppointmentStatusActions::cancel(),
                ])
                    ->label('More')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray'),
            ])
            /*
             | No bulk actions on purpose.
             |
             | Every appointment change emails a patient. A "confirm selected"
             | button is one mis-click away from sending forty people the wrong
             | message, and there is no undo for an email.
             */
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading('No appointments here')
            ->emptyStateDescription('When patients book through the website they will appear in this list.');
    }
}
