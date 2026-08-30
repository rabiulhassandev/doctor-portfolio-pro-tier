<?php

namespace App\Filament\Resources\Patients;

use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Models\Patient;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The people who book appointments.
 *
 * Read-only from the panel's point of view. Patients create their own accounts
 * through the website and manage their own details; staff look them up, read
 * their history, and can block an account that is abusing the booking form.
 *
 * Deliberately NOT editable by staff: an account's email is its login, and a
 * receptionist correcting a typo would lock the patient out of a system they
 * have appointments in.
 */
class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Patients';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact details')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Name'),

                    TextEntry::make('phone')
                        ->label('Phone')
                        ->copyable()
                        ->copyMessage('Phone number copied')
                        ->url(fn (Patient $record): string => 'tel:'.preg_replace('/[^0-9+]/', '', $record->phone)),

                    TextEntry::make('email')->label('Email')->copyable(),

                    TextEntry::make('date_of_birth')
                        ->label('Date of birth')
                        ->date('j F Y')
                        ->placeholder('Not given'),

                    TextEntry::make('age')
                        ->label('Age')
                        ->state(fn (Patient $record): string => $record->date_of_birth
                            ? $record->date_of_birth->age.' years'
                            : '—'),

                    TextEntry::make('gender')->label('Gender')->placeholder('Not given'),

                    TextEntry::make('address')
                        ->label('Address')
                        ->columnSpanFull()
                        ->placeholder('Not given'),
                ]),

            Section::make('Account')
                ->columns(3)
                ->schema([
                    TextEntry::make('created_at')->label('Registered')->date('j M Y'),
                    TextEntry::make('last_login_at')->label('Last signed in')->since()->placeholder('Never'),
                    TextEntry::make('appointments_count')
                        ->label('Appointments')
                        ->state(fn (Patient $record): int => $record->appointments()->count()),
                ]),

            Section::make('Clinical notes')
                ->description('Staff only. The patient never sees this.')
                ->schema([
                    TextEntry::make('medical_notes')
                        ->hiddenLabel()
                        ->placeholder('No notes recorded.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Patient $record): string => $record->phone)
                    ->weight('semibold'),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('appointments_count')
                    ->label('Appointments')
                    ->counts('appointments')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->date('j M Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Patient $record): string => $record->is_active ? 'Active' : 'Blocked')
                    ->color(fn (Patient $record): string => $record->is_active ? 'success' : 'danger'),
            ])
            ->filters([
                Filter::make('blocked')
                    ->label('Blocked accounts')
                    ->query(fn (Builder $query): Builder => $query->where('is_active', false)),
            ])
            ->recordActions([
                ViewAction::make(),
                static::toggleActive(),
            ])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('No patients registered yet')
            ->emptyStateDescription('Patients appear here once they create an account to book an appointment.');
    }

    /**
     * Block or unblock an account.
     *
     * Blocking rather than deleting: the appointment history hanging off this
     * patient is part of the practice's records and must survive.
     */
    protected static function toggleActive(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (Patient $record): string => $record->is_active ? 'Block' : 'Unblock')
            ->icon(fn (Patient $record): string => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
            ->color(fn (Patient $record): string => $record->is_active ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalHeading(fn (Patient $record): string => $record->is_active
                ? 'Block this account?'
                : 'Unblock this account?')
            ->modalDescription(fn (Patient $record): string => $record->is_active
                ? 'They will not be able to sign in or book. Their existing appointments and records are kept.'
                : 'They will be able to sign in and book again.')
            ->action(function (Patient $record): void {
                $record->forceFill(['is_active' => ! $record->is_active])->save();

                Notification::make()
                    ->success()
                    ->title($record->is_active ? 'Account unblocked' : 'Account blocked')
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatients::route('/'),
            'view' => ViewPatient::route('/{record}'),
        ];
    }
}
