<?php

namespace App\Filament\Resources\AvailabilityBlackouts\Pages;

use App\Filament\Resources\AvailabilityBlackouts\AvailabilityBlackoutResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAvailabilityBlackout extends EditRecord
{
    protected static string $resource = AvailabilityBlackoutResource::class;

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
