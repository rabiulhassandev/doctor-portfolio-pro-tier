<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Enums\DocumentKind;
use App\Filament\Resources\Appointments\Actions\AppointmentStatusActions;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Models\MedicalDocument;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * One appointment, with everything staff can do to it.
 *
 * The status buttons are the same objects the table uses — see
 * AppointmentStatusActions — so the two screens cannot drift apart.
 */
class ViewAppointment extends ViewRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AppointmentStatusActions::confirm(),
            AppointmentStatusActions::complete(),
            static::uploadDocument(),
            AppointmentStatusActions::reschedule(),
            AppointmentStatusActions::cancel(),
            static::editNotes(),
        ];
    }

    /**
     * Attach a prescription or report for the patient to collect.
     *
     * Files go to the private `medical` disk — outside public/, with no URL —
     * and are renamed to a ULID so a hostile upload filename can never become a
     * path. See config/filesystems.php and MedicalDocumentController.
     */
    protected static function uploadDocument(): Action
    {
        return Action::make('uploadDocument')
            ->label('Upload a document')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->schema([
                TextInput::make('title')
                    ->label('What is it?')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Prescription — 12 September')
                    ->helperText('This is the name the patient sees.'),

                Select::make('kind')
                    ->label('Type')
                    ->options(DocumentKind::class)
                    ->default(DocumentKind::Prescription->value)
                    ->required()
                    ->native(false),

                FileUpload::make('file')
                    ->label('The file')
                    ->required()
                    ->disk('medical')
                    ->visibility('private')
                    ->directory(fn (Appointment $record): string => 'patients/'.$record->patient_id)
                    // A ULID filename kills both guessing and path traversal
                    // through a hostile original name.
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => Str::ulid().'.'.$file->getClientOriginalExtension(),
                    )
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                    ->maxSize(10240)
                    /*
                     | The medical disk has serve => false, so it can produce no
                     | temporary URL and a preview would spin forever. Not
                     | cosmetic — turning this off is what stops that.
                     */
                    ->previewable(false)
                    ->storeFileNamesIn('original_filename')
                    ->helperText('PDF, JPG or PNG, up to 10 MB.'),

                Toggle::make('is_visible_to_patient')
                    ->label('Let the patient see it now')
                    ->default(true)
                    ->helperText('Turn this off to upload it now and release it later.'),
            ])
            ->modalHeading('Upload a document for this patient')
            ->modalSubmitActionLabel('Upload')
            ->action(function (Appointment $record, array $data): void {
                $path = $data['file'];

                MedicalDocument::create([
                    'patient_id' => $record->patient_id,
                    'appointment_id' => $record->getKey(),
                    'title' => $data['title'],
                    'kind' => $data['kind'],
                    'disk' => 'medical',
                    'path' => $path,
                    'original_filename' => $data['original_filename'][$path]
                        ?? $data['original_filename']
                        ?? basename($path),
                    'mime_type' => Storage::disk('medical')->mimeType($path) ?: null,
                    'size_bytes' => Storage::disk('medical')->size($path) ?: 0,
                    'uploaded_by_user_id' => Auth::id(),
                    'is_visible_to_patient' => (bool) ($data['is_visible_to_patient'] ?? true),
                ]);

                Notification::make()
                    ->success()
                    ->title('Document uploaded')
                    ->body(($data['is_visible_to_patient'] ?? true)
                        ? 'The patient can download it from their account.'
                        : 'It is saved but hidden from the patient until you release it.')
                    ->send();
            });
    }

    /** Staff-only notes about the visit. Never shown to the patient. */
    protected static function editNotes(): Action
    {
        return Action::make('editNotes')
            ->label('Private notes')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->fillForm(fn (Appointment $record): array => [
                'admin_notes' => $record->admin_notes,
            ])
            ->schema([
                Textarea::make('admin_notes')
                    ->label('Notes for the practice')
                    ->rows(6)
                    ->maxLength(2000)
                    ->helperText('Only staff can see this.'),
            ])
            ->modalHeading('Private notes')
            ->action(function (Appointment $record, array $data): void {
                // Not a status change, so it does not go through the workflow.
                $record->forceFill(['admin_notes' => $data['admin_notes']])->save();

                Notification::make()->success()->title('Notes saved')->send();
            });
    }
}
