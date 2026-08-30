<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Models\Appointment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The appointment detail screen.
 *
 * Ordered by what someone opening it actually needs: who and when at the top,
 * the money and the notes next, and the audit history last — important when it
 * is needed, noise the rest of the time.
 */
class AppointmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The appointment')
                ->columns(3)
                ->schema([
                    TextEntry::make('starts_at')
                        ->label('Date')
                        ->formatStateUsing(fn (Appointment $record): string => $record->dateLabel())
                        ->weight('semibold'),

                    TextEntry::make('time')
                        ->label('Time')
                        ->state(fn (Appointment $record): string => $record->timeLabel())
                        ->weight('semibold'),

                    TextEntry::make('status')
                        ->label('Status')
                        ->badge(),

                    TextEntry::make('reference')
                        ->label('Booking number')
                        ->copyable()
                        ->copyMessage('Copied'),

                    TextEntry::make('seat_no')
                        ->label('Position in slot')
                        ->visible(fn (Appointment $record): bool => $record->seat_no > 1),

                    TextEntry::make('created_at')
                        ->label('Booked')
                        ->dateTime('j M Y, g:i A')
                        ->since(),
                ]),

            Section::make('Patient')
                ->columns(3)
                ->schema([
                    TextEntry::make('patient_name')
                        ->label('Name'),

                    TextEntry::make('patient_phone')
                        ->label('Phone')
                        ->copyable()
                        ->copyMessage('Phone number copied')
                        ->url(fn (Appointment $record): string => 'tel:'.preg_replace('/[^0-9+]/', '', $record->patient_phone)),

                    TextEntry::make('patient_email')
                        ->label('Email')
                        ->copyable()
                        ->placeholder('Not given'),

                    TextEntry::make('notes')
                        ->label('What the patient told us')
                        ->columnSpanFull()
                        ->placeholder('Nothing was written.'),
                ]),

            Section::make('Fee and payment')
                ->columns(3)
                ->schema([
                    TextEntry::make('fee_amount')
                        ->label('Fee')
                        ->state(fn (Appointment $record): string => $record->formattedFee() ?? 'No fee set'),

                    TextEntry::make('payment_status')
                        ->label('Payment')
                        ->badge()
                        ->placeholder('—'),

                    TextEntry::make('successfulPayment.gateway_transaction_id')
                        ->label('Transaction reference')
                        ->copyable()
                        ->placeholder('—'),
                ]),

            Section::make('Private notes')
                ->description('Only staff can see this. It is never shown to the patient.')
                ->collapsed()
                ->schema([
                    TextEntry::make('admin_notes')
                        ->hiddenLabel()
                        ->placeholder('No notes yet.'),
                ]),

            Section::make('History')
                ->description('Every change to this appointment, and who made it.')
                ->collapsed()
                ->schema([
                    RepeatableEntry::make('statusLogs')
                        ->hiddenLabel()
                        ->columns(3)
                        ->schema([
                            TextEntry::make('summary')
                                ->hiddenLabel()
                                ->state(fn ($record): string => $record->summary())
                                ->columnSpan(2),

                            TextEntry::make('created_at')
                                ->hiddenLabel()
                                ->dateTime('j M Y, g:i A')
                                ->size('sm')
                                ->color('gray'),

                            TextEntry::make('reason')
                                ->label('Reason')
                                ->columnSpanFull()
                                ->hidden(fn ($record): bool => blank($record->reason)),
                        ]),
                ]),
        ]);
    }
}
