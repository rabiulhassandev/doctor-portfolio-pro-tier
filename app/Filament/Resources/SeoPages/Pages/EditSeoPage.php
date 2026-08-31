<?php

namespace App\Filament\Resources\SeoPages\Pages;

use App\Filament\Resources\SeoPages\SeoPageResource;
use App\Models\SeoPage;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditSeoPage extends EditRecord
{
    protected static string $resource = SeoPageResource::class;

    public function getTitle(): string
    {
        /** @var SeoPage $record */
        $record = $this->getRecord();

        return $record->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            // Straight to the page being tuned. Anyone writing a title wants to
            // look at what they are describing.
            Action::make('view')
                ->label('Open the page')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (SeoPage $record): ?string => $record->url(), shouldOpenInNewTab: true)
                ->visible(fn (SeoPage $record): bool => filled($record->url())),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
