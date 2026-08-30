<?php

namespace App\Filament\Resources\AvailabilitySlots\Pages;

use App\Filament\Resources\AvailabilitySlots\AvailabilitySlotResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAvailabilitySlot extends CreateRecord
{
    protected static string $resource = AvailabilitySlotResource::class;

    /** Back to the list after saving, rather than into the edit form. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
