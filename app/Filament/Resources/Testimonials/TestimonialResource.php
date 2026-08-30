<?php

namespace App\Filament\Resources\Testimonials;

use App\Filament\Forms\Components\PhotoUpload;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Filament\Resources\Testimonials\Pages\EditTestimonial;
use App\Filament\Resources\Testimonials\Pages\ListTestimonials;
use App\Models\Testimonial;
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
use UnitEnum;

/**
 * What patients have said about the practice.
 */
class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Website content';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('What the patient said')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Patient name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Use only what the patient agreed to. A first name and initial is often enough.'),

                    TextInput::make('role')
                        ->label('Description (optional)')
                        ->maxLength(255)
                        ->placeholder('Patient since 2019'),

                    Textarea::make('message')
                        ->label('Their words')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),

                    Select::make('rating')
                        ->label('Rating')
                        ->options([5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star'])
                        ->default(5)
                        ->required()
                        ->native(false),

                    TextInput::make('sort_order')
                        ->label('Order')
                        ->numeric()
                        ->default(0),

                    /*
                     | Photographs are optional and the demo ships without any.
                     | Putting a stranger's face beside a quote about their own
                     | health is a bigger ask than it looks, and most patients
                     | would decline if asked plainly. The public card falls
                     | back to initials in a circle.
                     */
                    PhotoUpload::make('photo')
                        ->label('Photograph (optional)')
                        ->directory('testimonials')
                        ->avatar()
                        ->columnSpanFull()
                        ->guidance('Only with the patient\'s explicit permission. Without one we show their initials, which looks perfectly deliberate.'),

                    Toggle::make('is_published')
                        ->label('Published')
                        ->default(true)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Testimonial $record): string => str($record->message)->limit(80)->toString())
                    ->weight('semibold')
                    ->wrap(),

                TextColumn::make('rating')
                    ->label('Rating')
                    ->formatStateUsing(fn (int $state): string => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color('warning'),

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
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading('No testimonials yet')
            ->emptyStateDescription('Add what patients have told you — with their permission — and they will appear on the home page.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTestimonials::route('/'),
            'create' => CreateTestimonial::route('/create'),
            'edit' => EditTestimonial::route('/{record}/edit'),
        ];
    }
}
