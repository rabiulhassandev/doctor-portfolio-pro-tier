<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\Actions\AppointmentStatusActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Bookings nobody has answered yet.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS THE FIRST THING ON THE DASHBOARD
 * ---------------------------------------------------------------------------
 *
 * A patient who books online and hears nothing telephones the chamber, and if
 * that goes on long enough they book somewhere else. It is the only queue in
 * this application where delay costs the practice something directly, and it
 * was previously a number on a tile that you had to click through to act on.
 *
 * Now the list is on the dashboard with the confirm button already on each row.
 * The whole interaction is: open the panel, press Confirm three times, done.
 *
 * The widget hides itself entirely when the queue is empty, which is the
 * normal state — a dashboard permanently carrying an empty "nothing to do"
 * panel trains people to scroll past the place where work appears.
 */
class AwaitingConfirmation extends TableWidget
{
    /** Above the chart and today's list: this is the one with a deadline. */
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::baseQuery()->exists();
    }

    /**
     * Upcoming and unanswered.
     *
     * Past-dated pending rows are excluded deliberately. They are not work
     * waiting to be done — the appointment has already been and gone — and
     * including them would leave a queue nobody can ever empty, which is the
     * fastest way to make a dashboard panel invisible.
     */
    private static function baseQuery(): Builder
    {
        return Appointment::query()
            ->where('status', AppointmentStatus::Pending->value)
            ->where('starts_at', '>=', now());
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Waiting for confirmation')
            ->description('These patients have booked and are waiting to hear back.')
            ->query(static::baseQuery()->orderBy('starts_at'))
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Appointment')
                    ->formatStateUsing(fn (Appointment $record): string => $record->startsAtLocal()->format('D j M, g:i A'))
                    ->description(fn (Appointment $record): string => $record->startsAtLocal()->diffForHumans())
                    ->weight('semibold')
                    /*
                     | Red for anything inside twenty-four hours. A booking for
                     | tomorrow morning that nobody has answered is a different
                     | problem from one for next month, and the sort order alone
                     | does not say so loudly enough.
                     */
                    ->color(fn (Appointment $record): ?string => $record->starts_at->isBefore(now()->addDay())
                        ? 'danger'
                        : null),

                TextColumn::make('patient_name')
                    ->label('Patient')
                    ->description(fn (Appointment $record): ?string => $record->patient_phone)
                    ->searchable()
                    ->copyable()
                    ->copyableState(fn (Appointment $record): string => (string) $record->patient_phone)
                    ->copyMessage('Phone number copied'),

                TextColumn::make('created_at')
                    ->label('Booked')
                    ->since()
                    ->tooltip(fn (Appointment $record): string => $record->created_at->format('j M Y, g:i A'))
                    ->toggleable(),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->placeholder('—'),
            ])
            ->recordActions([
                // The same action object the appointments table uses, so the
                // two behave identically — same dialogue, same email.
                AppointmentStatusActions::confirm(),
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Appointment $record): string => url('/admin/appointments/'.$record->getRouteKey())),
            ])
            ->headerActions([
                Action::make('all')
                    ->label('See all')
                    ->icon('heroicon-m-arrow-right')
                    ->color('gray')
                    ->link()
                    ->url(AppointmentResource::getUrl('index', ['activeTab' => 'pending'])),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Nothing waiting');
    }

    /** Widgets get their own query; keep it from being reordered by the table. */
    protected function getTableQuery(): ?Builder
    {
        return null;
    }
}
