<?php

namespace App\Filament\Resources\HealthVideos\Pages;

use App\Filament\Resources\HealthVideos\HealthVideoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHealthVideo extends CreateRecord
{
    protected static string $resource = HealthVideoResource::class;

    /** Back to the list after saving, rather than into the edit form. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
