<?php

namespace App\Filament\Resources\MedicalDocuments\Pages;

use App\Filament\Resources\MedicalDocuments\MedicalDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMedicalDocument extends EditRecord
{
    protected static string $resource = MedicalDocumentResource::class;

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
