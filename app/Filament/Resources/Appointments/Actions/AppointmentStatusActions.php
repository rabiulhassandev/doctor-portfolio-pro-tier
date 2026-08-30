<?php

namespace App\Filament\Resources\Appointments\Actions;

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use App\Exceptions\InvalidStatusTransition;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Services\Booking\AppointmentWorkflow;
use App\Services\Booking\AvailabilityService;
use App\Support\Clock;
use App\Support\Slot;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;

/**
 * The confirm / complete / cancel / reschedule buttons.
 *
 * Static factories rather than inline definitions, because the same four
 * actions appear on the appointment table AND on the appointment detail page.
 * Defining them twice is how the two screens quietly start behaving
 * differently — one asking for confirmation, the other not.
 *
 * Every action body is a single call into AppointmentWorkflow. All the rules
 * about which transitions are legal, who may make them, what gets logged and
 * who gets emailed live there, so these buttons stay presentation only.
 */
class AppointmentStatusActions
{
    public static function confirm(): Action
    {
        return Action::make('confirm')
            ->label('Confirm')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            // Only offered when it is actually a legal move, so a receptionist
            // never presses a button that then refuses them.
            ->visible(fn (Appointment $record): bool => $record->status->canTransitionTo(AppointmentStatus::Confirmed))
            ->requiresConfirmation()
            ->modalHeading('Confirm this appointment?')
            ->modalDescription(fn (Appointment $record): string => sprintf(
                'The patient will be emailed to say their appointment on %s at %s is confirmed.',
                $record->dateLabel(),
                $record->startsAtLocal()->format('g:i A'),
            ))
            ->modalSubmitActionLabel('Yes, confirm it')
            ->action(function (Appointment $record): void {
                static::run(
                    fn () => app(AppointmentWorkflow::class)->confirm($record, BookingActor::Admin),
                    'Appointment confirmed',
                    'The patient has been notified.',
                );
            });
    }

    public static function complete(): Action
    {
        return Action::make('complete')
            ->label('Mark as seen')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('info')
            ->visible(fn (Appointment $record): bool => $record->status->canTransitionTo(AppointmentStatus::Completed))
            ->requiresConfirmation()
            ->modalHeading('Mark this appointment as completed?')
            ->modalDescription('Use this once the patient has been seen. You can then upload their prescription or reports.')
            ->modalSubmitActionLabel('Yes, they have been seen')
            ->action(function (Appointment $record): void {
                static::run(
                    fn () => app(AppointmentWorkflow::class)->complete($record, BookingActor::Admin),
                    'Marked as completed',
                    'You can now upload documents against this visit.',
                );
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Appointment $record): bool => $record->status->canTransitionTo(AppointmentStatus::Cancelled))
            ->schema([
                Textarea::make('reason')
                    ->label('Reason for cancelling')
                    ->rows(3)
                    ->maxLength(500)
                    ->helperText('This is included in the email to the patient. Leave it empty to say nothing.'),
            ])
            ->requiresConfirmation()
            ->modalHeading('Cancel this appointment?')
            ->modalDescription('The patient will be emailed, and the slot will be freed for someone else.')
            ->modalSubmitActionLabel('Yes, cancel it')
            ->action(function (Appointment $record, array $data): void {
                static::run(
                    fn () => app(AppointmentWorkflow::class)->cancel(
                        $record,
                        BookingActor::Admin,
                        $data['reason'] ?? null,
                    ),
                    'Appointment cancelled',
                    'The patient has been notified and the slot is free again.',
                );
            });
    }

    /**
     * Move an appointment to a different time.
     *
     * The dropdown is built from real availability, so staff can only move a
     * patient into a slot that genuinely has room. Picking a date reloads the
     * times — hence ->live() on the date field.
     */
    public static function reschedule(): Action
    {
        return Action::make('reschedule')
            ->label('Reschedule')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->visible(fn (Appointment $record): bool => $record->status->canTransitionTo(AppointmentStatus::Rescheduled))
            ->schema([
                Select::make('date')
                    ->label('New date')
                    ->required()
                    ->native(false)
                    ->live()
                    // Only dates that still have a free place.
                    ->options(fn (): array => app(AvailabilityService::class)
                        ->bookableDates()
                        ->mapWithKeys(fn ($date): array => [
                            $date->toDateString() => $date->format('l, j F Y'),
                        ])
                        ->all())
                    ->afterStateUpdated(fn (Set $set) => $set('slot', null))
                    ->helperText('Only dates with a free appointment are listed.'),

                Select::make('slot')
                    ->label('New time')
                    ->required()
                    ->native(false)
                    ->options(function (Get $get): array {
                        $date = $get('date');

                        if (blank($date)) {
                            return [];
                        }

                        return app(AvailabilityService::class)
                            ->slotsForDate(Clock::parse($date))
                            ->mapWithKeys(fn (Slot $slot): array => [
                                $slot->key() => $slot->rangeLabel()
                                    .($slot->scarcityLabel() ? ' — '.$slot->scarcityLabel() : ''),
                            ])
                            ->all();
                    })
                    ->hidden(fn (Get $get): bool => blank($get('date'))),

                Textarea::make('reason')
                    ->label('Why is it moving?')
                    ->rows(2)
                    ->maxLength(500)
                    ->helperText('Included in the email to the patient. Optional.'),
            ])
            ->modalHeading('Move this appointment')
            ->modalDescription('The patient gets one email telling them the new time — not a cancellation followed by a new booking.')
            ->modalSubmitActionLabel('Move the appointment')
            ->action(function (Appointment $record, array $data): void {
                static::run(
                    fn () => app(AppointmentWorkflow::class)->reschedule(
                        $record,
                        Clock::parse($data['slot']),
                        BookingActor::Admin,
                        $data['reason'] ?? null,
                    ),
                    'Appointment moved',
                    'The patient has been emailed the new time.',
                );
            });
    }

    /**
     * Run a workflow call and turn its outcome into a Filament notification.
     *
     * The two exceptions the workflow throws are both *expected* outcomes with
     * messages already written for a human — a slot filling up between page
     * load and button press is normal, not a fault. Anything else is a real
     * problem and is reported as one.
     */
    private static function run(callable $operation, string $title, string $body): void
    {
        try {
            $operation();
        } catch (InvalidStatusTransition|SlotUnavailableException $e) {
            Notification::make()
                ->warning()
                ->title('That could not be done')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            return;
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title('Something went wrong')
                ->body('The change was not saved. Please try again.')
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($title)
            ->body($body)
            ->send();
    }
}
