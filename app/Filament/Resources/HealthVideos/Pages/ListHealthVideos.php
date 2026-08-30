<?php

namespace App\Filament\Resources\HealthVideos\Pages;

use App\Filament\Resources\HealthVideos\HealthVideoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHealthVideos extends ListRecords
{
    protected static string $resource = HealthVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
