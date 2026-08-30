<?php

namespace App\Filament\Resources\AvailabilitySlots\Schemas;

use App\Enums\AvailabilityScope;
use App\Models\AvailabilitySlot;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

/**
 * The form for one availability rule.
 *
 * The hardest thing about this screen is that a doctor does not think in
 * "rules" — they think "I sit Sunday evenings". So the wording avoids the word
 * entirely, the scope is a two-option radio rather than an inference from which
 * box was left blank, and the summary at the bottom tells them how many
 * appointments they have just created before they save it.
 */
class AvailabilitySlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('When do you see patients?')
                ->schema([
                    Radio::make('scope')
                        ->label('This applies')
                        ->options(AvailabilityScope::class)
                        ->descriptions(collect(AvailabilityScope::cases())
                            ->mapWithKeys(fn (AvailabilityScope $scope): array => [
                                $scope->value => $scope->description(),
                            ])
                            ->all())
                        ->default(AvailabilityScope::Weekly->value)
                        ->required()
                        ->live()
                        ->inline(false),

                    Select::make('day_of_week')
                        ->label('Day of the week')
                        ->options(AvailabilitySlot::WEEKDAYS)
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('scope') === AvailabilityScope::Weekly->value)
                        ->visible(fn (Get $get): bool => $get('scope') === AvailabilityScope::Weekly->value),

                    DatePicker::make('specific_date')
                        ->label('Date')
                        ->native(false)
                        ->minDate(now()->startOfDay())
                        ->required(fn (Get $get): bool => $get('scope') === AvailabilityScope::Date->value)
                        ->visible(fn (Get $get): bool => $get('scope') === AvailabilityScope::Date->value),

                    Toggle::make('replaces_recurring')
                        ->label('Replace my normal hours on this date')
                        ->default(true)
                        ->visible(fn (Get $get): bool => $get('scope') === AvailabilityScope::Date->value)
                        ->helperText('On: these are the ONLY hours for that date. Off: these are in addition to your usual ones.'),
                ]),

            Section::make('Times')
                ->columns(2)
                ->schema([
                    TextInput::make('start_time')
                        ->label('From')
                        ->type('time')
                        ->required()
                        ->default('18:00'),

                    TextInput::make('end_time')
                        ->label('Until')
                        ->type('time')
                        ->required()
                        ->default('21:00')
                        ->rule(fn (Get $get): callable => function (string $attribute, $value, callable $fail) use ($get): void {
                            if (blank($get('start_time')) || blank($value)) {
                                return;
                            }

                            // Midnight is a legitimate end time, meaning the end
                            // of the evening rather than the start of it.
                            if ($value !== '00:00' && Carbon::parse($value)->lte(Carbon::parse($get('start_time')))) {
                                $fail('The finish time needs to be after the start time.');
                            }
                        }),

                    TextInput::make('slot_duration')
                        ->label('How long is each appointment?')
                        ->numeric()
                        ->suffix('minutes')
                        ->required()
                        ->default(30)
                        ->minValue(AvailabilitySlot::MIN_DURATION)
                        ->maxValue(480)
                        ->live(onBlur: true)
                        ->helperText('The block above is divided into appointments of this length.'),

                    TextInput::make('max_bookings_per_slot')
                        ->label('Patients per appointment time')
                        ->numeric()
                        ->required()
                        ->default(1)
                        ->minValue(1)
                        ->maxValue(50)
                        ->live(onBlur: true)
                        ->helperText('Leave at 1 for proper appointments. Raise it if you run a serial system where several patients are given the same time.'),
                ]),

            Section::make('Details')
                ->columns(2)
                ->schema([
                    TextInput::make('label')
                        ->label('Name for this block')
                        ->maxLength(255)
                        ->placeholder('Evening chamber')
                        ->helperText('Only you see this. It makes the list easier to scan.'),

                    Toggle::make('is_active')
                        ->label('Currently in use')
                        ->default(true)
                        ->helperText('Turn off to stop taking bookings for this block without deleting it.'),
                ]),
        ]);
    }
}
