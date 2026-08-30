<?php

namespace App\Filament\Resources\MedicalDocuments\Pages;

use App\Filament\Resources\MedicalDocuments\MedicalDocumentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateMedicalDocument extends CreateRecord
{
    protected static string $resource = MedicalDocumentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Fill in the facts about the file that the form cannot ask for.
     *
     * Size and MIME type are read from the stored file rather than trusted from
     * the browser, which reports whatever it likes.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $disk = Storage::disk('medical');

        $data['disk'] = 'medical';
        $data['uploaded_by_user_id'] = Auth::id();
        $data['mime_type'] = $disk->exists($data['path']) ? $disk->mimeType($data['path']) : null;
        $data['size_bytes'] = $disk->exists($data['path']) ? $disk->size($data['path']) : 0;

        return $data;
    }
}
