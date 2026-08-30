<?php

namespace App\Filament\Resources\AvailabilitySlots;

use App\Filament\Resources\AvailabilitySlots\Pages\CreateAvailabilitySlot;
use App\Filament\Resources\AvailabilitySlots\Pages\EditAvailabilitySlot;
use App\Filament\Resources\AvailabilitySlots\Pages\ListAvailabilitySlots;
use App\Filament\Resources\AvailabilitySlots\Schemas\AvailabilitySlotForm;
use App\Filament\Resources\AvailabilitySlots\Tables\AvailabilitySlotsTable;
use App\Models\AvailabilitySlot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * When the doctor sees patients.
 *
 * These rules are what the public booking calendar is built from — nothing
 * here means no appointments can be booked at all, which is why the empty
 * state says so plainly.
 */
class AvailabilitySlotResource extends Resource
{
    protected static ?string $model = AvailabilitySlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Practice';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'My hours';

    protected static ?string $modelLabel = 'set of hours';

    protected static ?string $pluralModelLabel = 'my hours';

    public static function form(Schema $schema): Schema
    {
        return AvailabilitySlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AvailabilitySlotsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAvailabilitySlots::route('/'),
            'create' => CreateAvailabilitySlot::route('/create'),
            'edit' => EditAvailabilitySlot::route('/{record}/edit'),
        ];
    }
}
