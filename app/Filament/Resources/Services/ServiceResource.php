<?php

namespace App\Filament\Resources\Services;

use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The treatments and procedures the practice offers.
 */
class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Website content';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * The icons offered in the dropdown.
     *
     * A fixed list rather than free text: a mistyped icon name renders nothing
     * at all, and the doctor has no way to know which names exist. These are
     * chosen to cover the common medical specialities.
     *
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            'heroicon-o-heart' => 'Heart',
            'heroicon-o-beaker' => 'Laboratory / tests',
            'heroicon-o-clipboard-document-check' => 'Check-up',
            'heroicon-o-chart-bar' => 'Monitoring / results',
            'heroicon-o-bolt' => 'ECG / electrical',
            'heroicon-o-shield-check' => 'Prevention',
            'heroicon-o-user-group' => 'Family / group',
            'heroicon-o-academic-cap' => 'Advice / counselling',
            'heroicon-o-sparkles' => 'General',
            'heroicon-o-clock' => 'Follow-up',
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The service')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, $set): void {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),

                    TextInput::make('slug')
                        ->label('Web address')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Select::make('icon')
                        ->label('Icon')
                        ->options(static::iconOptions())
                        ->native(false)
                        ->searchable()
                        ->helperText('Shown on the card. Picked from a list so it always renders.'),

                    TextInput::make('sort_order')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first. You can also drag rows in the list.'),

                    Textarea::make('summary')
                        ->label('One-line summary')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull()
                        ->helperText('Shown on the card on the home page and services page.'),

                    Textarea::make('description')
                        ->label('Full description')
                        ->rows(6)
                        ->columnSpanFull()
                        ->helperText('Optional. Shown on the services page under the summary.'),
                ]),

            Section::make('Where it appears')
                ->columns(2)
                ->schema([
                    Toggle::make('is_published')
                        ->label('Published')
                        ->default(true),

                    Toggle::make('is_featured')
                        ->label('Show on the home page')
                        ->helperText('Only a few services belong on the home page — the rest still appear on the services page.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('icon')
                    ->label('')
                    ->icon(fn (?string $state): ?string => $state)
                    ->state(fn (): string => ''),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Service $record): ?string => $record->shortSummary(70))
                    ->weight('semibold')
                    ->wrap(),

                IconColumn::make('is_featured')
                    ->label('Home page')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_published')
                    ->label('Live')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-sparkles')
            ->emptyStateHeading('No services listed yet')
            ->emptyStateDescription('Add what you treat, and it will appear on the home page and the services page.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}
