<?php

namespace App\Filament\Resources\HealthVideos\Pages;

use App\Filament\Resources\HealthVideos\HealthVideoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHealthVideo extends EditRecord
{
    protected static string $resource = HealthVideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
