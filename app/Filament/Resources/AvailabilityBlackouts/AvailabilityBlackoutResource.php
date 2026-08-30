<?php

namespace App\Filament\Resources\AvailabilityBlackouts;

use App\Filament\Resources\AvailabilityBlackouts\Pages\CreateAvailabilityBlackout;
use App\Filament\Resources\AvailabilityBlackouts\Pages\EditAvailabilityBlackout;
use App\Filament\Resources\AvailabilityBlackouts\Pages\ListAvailabilityBlackouts;
use App\Models\AvailabilityBlackout;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Days the doctor is away.
 *
 * Small enough that the form and table live in the resource rather than in
 * separate Schemas/ and Tables/ classes — splitting four fields across three
 * files would be ceremony rather than structure.
 *
 * A blackout overrides every availability rule. See AvailabilityService.
 */
class AvailabilityBlackoutResource extends Resource
{
    protected static ?string $model = AvailabilityBlackout::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = 'Practice';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Days off';

    protected static ?string $modelLabel = 'day off';

    protected static ?string $pluralModelLabel = 'days off';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('When are you away?')
                ->description('No appointments can be booked on these dates, whatever your normal hours say.')
                ->columns(2)
                ->schema([
                    DatePicker::make('starts_on')
                        ->label('First day away')
                        ->required()
                        ->native(false)
                        ->default(now()),

                    DatePicker::make('ends_on')
                        ->label('Last day away')
                        ->required()
                        ->native(false)
                        ->default(now())
                        ->helperText('Set this to the same day for a single day off.'),

                    TextInput::make('reason')
                        ->label('Reason (optional)')
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->placeholder('Eid holiday')
                        ->helperText('Patients see this on the booking calendar, which saves them telephoning to ask. Leave it empty to say nothing.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('starts_on')
                    ->label('Dates')
                    ->state(fn (AvailabilityBlackout $record): string => $record->dateRangeLabel())
                    ->description(fn (AvailabilityBlackout $record): ?string => $record->reason)
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('length')
                    ->label('Length')
                    ->state(function (AvailabilityBlackout $record): string {
                        $days = (int) $record->starts_on->diffInDays($record->ends_on) + 1;

                        return $days === 1 ? '1 day' : "{$days} days";
                    })
                    ->alignCenter()
                    ->color('gray'),

                TextColumn::make('ends_on')
                    ->label('Status')
                    ->badge()
                    ->state(fn (AvailabilityBlackout $record): string => $record->ends_on->isPast() ? 'Past' : 'Upcoming')
                    ->color(fn (AvailabilityBlackout $record): string => $record->ends_on->isPast() ? 'gray' : 'warning'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-sun')
            ->emptyStateHeading('No days off booked')
            ->emptyStateDescription('Add the dates you will be away and the booking calendar will close them automatically.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAvailabilityBlackouts::route('/'),
            'create' => CreateAvailabilityBlackout::route('/create'),
            'edit' => EditAvailabilityBlackout::route('/{record}/edit'),
        ];
    }
}
