<?php

namespace App\Filament\Resources\AvailabilitySlots\Tables;

use App\Enums\AvailabilityScope;
use App\Models\AvailabilitySlot;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AvailabilitySlotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('day_of_week')
            ->columns([
                TextColumn::make('when')
                    ->label('When')
                    ->state(fn (AvailabilitySlot $record): string => $record->whenLabel())
                    ->description(fn (AvailabilitySlot $record): ?string => $record->label)
                    ->weight('semibold'),

                TextColumn::make('scope')
                    ->label('Repeats')
                    ->badge(),

                TextColumn::make('times')
                    ->label('Hours')
                    ->state(fn (AvailabilitySlot $record): string => $record->timeRangeLabel()),

                TextColumn::make('slot_duration')
                    ->label('Each')
                    ->suffix(' min')
                    ->alignCenter(),

                /*
                 | The number that stops mistakes. "6pm to 9pm in ten-minute
                 | slots" is eighteen appointments an evening, and seeing that
                 | in the list is how a doctor notices before patients book them.
                 */
                TextColumn::make('slot_count')
                    ->label('Appointments')
                    ->state(fn (AvailabilitySlot $record): string => $record->slotCount()
                        .($record->max_bookings_per_slot > 1 ? ' × '.$record->max_bookings_per_slot.' patients' : ''))
                    ->alignCenter()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('In use')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('scope')
                    ->label('Type')
                    ->options(AvailabilityScope::class),

                SelectFilter::make('is_active')
                    ->label('In use')
                    ->options([1 => 'Yes', 0 => 'No']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading('You have not set your hours yet')
            ->emptyStateDescription('Add the times you see patients and the website will start offering them as appointments.');
    }
}
