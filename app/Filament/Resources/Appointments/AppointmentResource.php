<?php

namespace App\Filament\Resources\Appointments;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Resources\Appointments\Schemas\AppointmentInfolist;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use App\Models\Appointment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The appointment book — the screen the practice lives in.
 *
 * Read and act, not create and edit. Appointments are made by patients through
 * the booking flow, which allocates seats and enforces capacity; letting staff
 * type one straight into the database would bypass all of that. Changing an
 * appointment is done with the status actions instead.
 */
class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Practice';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'patient_name';

    protected static ?string $modelLabel = 'appointment';

    /** How many bookings are waiting for the doctor to confirm them. */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::query()
            ->where('status', AppointmentStatus::Pending->value)
            ->where('starts_at', '>=', now())
            ->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Bookings waiting for your confirmation';
    }

    public static function infolist(Schema $schema): Schema
    {
        return AppointmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AppointmentsTable::configure($table);
    }

    /**
     * Patients create appointments, staff never do.
     *
     * A hand-typed appointment would skip the availability rules, the capacity
     * check and the seat allocation — which is precisely the machinery that
     * stops two people being given the same time.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['patient_name', 'patient_phone', 'reference'];
    }

    public static function getGlobalSearchResultDetails($record): array
    {
        return [
            'When' => $record->dateLabel().', '.$record->startsAtLocal()->format('g:i A'),
            'Status' => $record->status->getLabel(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppointments::route('/'),
            'view' => ViewAppointment::route('/{record}'),
        ];
    }
}
