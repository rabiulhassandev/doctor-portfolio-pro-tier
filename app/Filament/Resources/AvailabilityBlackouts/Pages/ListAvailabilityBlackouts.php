<?php

namespace App\Filament\Resources\AvailabilityBlackouts\Pages;

use App\Filament\Resources\AvailabilityBlackouts\AvailabilityBlackoutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAvailabilityBlackouts extends ListRecords
{
    protected static string $resource = AvailabilityBlackoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
