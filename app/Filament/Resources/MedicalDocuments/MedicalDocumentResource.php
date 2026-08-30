<?php

namespace App\Filament\Resources\MedicalDocuments;

use App\Enums\DocumentKind;
use App\Filament\Resources\MedicalDocuments\Pages\CreateMedicalDocument;
use App\Filament\Resources\MedicalDocuments\Pages\EditMedicalDocument;
use App\Filament\Resources\MedicalDocuments\Pages\ListMedicalDocuments;
use App\Models\Appointment;
use App\Models\MedicalDocument;
use App\Models\Patient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Prescriptions, reports and invoices issued to patients.
 *
 * The files live on the private `medical` disk, outside public/ and with no URL
 * at all. The only way to one is MedicalDocumentController, which authorises
 * every request — which is also why there is no "preview" or "open" action in
 * this table.
 *
 * Most documents are uploaded from the appointment screen, where the patient is
 * already in context. This resource exists for the rest: a lab report that
 * arrives days later, or one that belongs to no particular visit.
 */
class MedicalDocumentResource extends Resource
{
    protected static ?string $model = MedicalDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Patients';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Documents';

    protected static ?string $modelLabel = 'document';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Who is it for?')
                ->columns(2)
                ->schema([
                    Select::make('patient_id')
                        ->label('Patient')
                        ->required()
                        ->relationship('patient', 'name')
                        ->searchable(['name', 'email', 'phone'])
                        ->preload()
                        ->live()
                        ->getOptionLabelFromRecordUsing(fn (Patient $record): string => "{$record->name} — {$record->phone}"),

                    Select::make('appointment_id')
                        ->label('From which visit? (optional)')
                        ->searchable()
                        ->preload()
                        // Only that patient's own appointments, so a document
                        // cannot be filed against somebody else's visit.
                        ->options(fn (Get $get): array => blank($get('patient_id'))
                            ? []
                            : Appointment::query()
                                ->where('patient_id', $get('patient_id'))
                                ->orderByDesc('starts_at')
                                ->get()
                                ->mapWithKeys(fn (Appointment $a): array => [
                                    $a->getKey() => $a->dateLabel().' — '.$a->startsAtLocal()->format('g:i A'),
                                ])
                                ->all())
                        ->helperText('Leave empty for a document that does not belong to a particular visit.'),
                ]),

            Section::make('The document')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('What is it?')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Blood test results')
                        ->helperText('This is the name the patient sees.'),

                    Select::make('kind')
                        ->label('Type')
                        ->options(DocumentKind::class)
                        ->default(DocumentKind::Report->value)
                        ->required()
                        ->native(false),

                    FileUpload::make('path')
                        ->label('File')
                        ->required()
                        ->columnSpanFull()
                        ->disk('medical')
                        ->visibility('private')
                        ->directory(fn (Get $get): string => 'patients/'.($get('patient_id') ?: 'unfiled'))
                        // A ULID filename kills guessing and stops a hostile
                        // upload filename ever becoming a path.
                        ->getUploadedFileNameForStorageUsing(
                            fn (TemporaryUploadedFile $file): string => Str::ulid().'.'.$file->getClientOriginalExtension(),
                        )
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(10240)
                        /*
                         | The medical disk has serve => false and therefore no
                         | temporary URL, so a preview would spin forever.
                         | Turning it off is what prevents that.
                         */
                        ->previewable(false)
                        ->storeFileNamesIn('original_filename')
                        ->helperText('PDF, JPG or PNG, up to 10 MB. Stored privately — only this patient can download it.'),

                    Toggle::make('is_visible_to_patient')
                        ->label('Let the patient see it')
                        ->default(true)
                        ->columnSpanFull()
                        ->helperText('Turn off to upload now and release it once you have reviewed it.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->description(fn (MedicalDocument $record): string => $record->original_filename)
                    ->weight('semibold')
                    ->icon(fn (MedicalDocument $record): string => $record->kind->getIcon()),

                TextColumn::make('patient.name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable()
                    ->description(fn (MedicalDocument $record): ?string => $record->patient?->phone),

                TextColumn::make('kind')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('size_bytes')
                    ->label('Size')
                    ->state(fn (MedicalDocument $record): string => $record->formattedSize())
                    ->toggleable(),

                IconColumn::make('is_visible_to_patient')
                    ->label('Released')
                    ->boolean()
                    ->tooltip('Whether the patient can see it yet'),

                TextColumn::make('download_count')
                    ->label('Downloads')
                    ->alignCenter()
                    // The chamber always ends up asking "did they get it?".
                    ->description(fn (MedicalDocument $record): ?string => $record->downloaded_at?->diffForHumans())
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->date('j M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Type')
                    ->options(DocumentKind::class),

                SelectFilter::make('is_visible_to_patient')
                    ->label('Released')
                    ->options([1 => 'Released', 0 => 'Held back']),
            ])
            ->recordActions([
                static::toggleVisibility(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-document-text')
            ->emptyStateHeading('No documents uploaded')
            ->emptyStateDescription('Upload prescriptions and reports here, or from the appointment they belong to. Patients download them from their own account.');
    }

    /** Release a staged document, or pull one back. */
    protected static function toggleVisibility(): Action
    {
        return Action::make('toggleVisibility')
            ->label(fn (MedicalDocument $record): string => $record->is_visible_to_patient ? 'Hide' : 'Release')
            ->icon(fn (MedicalDocument $record): string => $record->is_visible_to_patient ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
            ->color(fn (MedicalDocument $record): string => $record->is_visible_to_patient ? 'gray' : 'success')
            ->requiresConfirmation()
            ->modalHeading(fn (MedicalDocument $record): string => $record->is_visible_to_patient
                ? 'Hide this from the patient?'
                : 'Release this to the patient?')
            ->action(function (MedicalDocument $record): void {
                $record->forceFill([
                    'is_visible_to_patient' => ! $record->is_visible_to_patient,
                ])->save();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedicalDocuments::route('/'),
            'create' => CreateMedicalDocument::route('/create'),
            'edit' => EditMedicalDocument::route('/{record}/edit'),
        ];
    }
}
