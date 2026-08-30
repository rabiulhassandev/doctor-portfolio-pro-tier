<?php

namespace App\Filament\Resources\AvailabilitySlots\Pages;

use App\Filament\Resources\AvailabilitySlots\AvailabilitySlotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAvailabilitySlots extends ListRecords
{
    protected static string $resource = AvailabilitySlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
