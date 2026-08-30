<?php

namespace App\Filament\Resources\AvailabilityBlackouts\Pages;

use App\Filament\Resources\AvailabilityBlackouts\AvailabilityBlackoutResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAvailabilityBlackout extends CreateRecord
{
    protected static string $resource = AvailabilityBlackoutResource::class;

    /** Back to the list after saving, rather than into the edit form. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
