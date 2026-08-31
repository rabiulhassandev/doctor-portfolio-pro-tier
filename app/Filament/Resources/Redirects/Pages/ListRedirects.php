<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRedirects extends ListRecords
{
    protected static string $resource = RedirectResource::class;

    public function getSubheading(): ?string
    {
        return 'Keep links from an old website working, so the standing those pages built up is not thrown away.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add a redirect'),
        ];
    }
}
