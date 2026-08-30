<?php

namespace App\Filament\Resources\AvailabilitySlots\Pages;

use App\Filament\Resources\AvailabilitySlots\AvailabilitySlotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAvailabilitySlot extends EditRecord
{
    protected static string $resource = AvailabilitySlotResource::class;

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
