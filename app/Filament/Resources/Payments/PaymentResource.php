<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The money ledger.
 *
 * Entirely read-only. Payment rows are written by the gateway callbacks after
 * the provider has confirmed the transaction; a hand-edited amount here would
 * make the practice's records disagree with the processor's, which is the one
 * thing you never want when a patient disputes a charge.
 *
 * Refunds are issued from the gateway's own dashboard, not from here.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Practice';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('j M Y, g:i A')
                    ->sortable()
                    ->description(fn (Payment $record): string => $record->created_at->diffForHumans()),

                TextColumn::make('appointment.patient_name')
                    ->label('Patient')
                    ->searchable()
                    ->description(fn (Payment $record): ?string => $record->appointment?->reference),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->state(fn (Payment $record): string => $record->formattedAmount())
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('gateway')
                    ->label('Method')
                    ->badge()
                    ->formatStateUsing(fn (Payment $record): string => $record->gatewayLabel())
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('reference')
                    ->label('Our reference')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Reference copied')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size('sm'),

                TextColumn::make('gateway_transaction_id')
                    ->label('Gateway reference')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable()
                    ->size('sm'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PaymentStatus::class)
                    ->multiple(),

                SelectFilter::make('gateway')
                    ->label('Method')
                    ->options(fn (): array => collect(config('booking.payment.gateways', []))
                        ->map(fn (array $config, string $key): string => $config['label'] ?? $key)
                        ->all()),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateIcon('heroicon-o-banknotes')
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Online payments appear here as soon as the gateway confirms them.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
        ];
    }
}
