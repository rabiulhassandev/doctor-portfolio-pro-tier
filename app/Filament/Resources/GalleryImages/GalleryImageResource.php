<?php

namespace App\Filament\Resources\GalleryImages;

use App\Filament\Forms\Components\PhotoUpload;
use App\Filament\Resources\GalleryImages\Pages\CreateGalleryImage;
use App\Filament\Resources\GalleryImages\Pages\EditGalleryImage;
use App\Filament\Resources\GalleryImages\Pages\ListGalleryImages;
use App\Models\GalleryImage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Photographs of the chamber, the team and the equipment.
 */
class GalleryImageResource extends Resource
{
    protected static ?string $model = GalleryImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Website content';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Gallery';

    protected static ?string $modelLabel = 'gallery photo';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Photograph')
                ->schema([
                    PhotoUpload::make('image')
                        ->label('Photo')
                        ->required()
                        ->directory('gallery')
                        ->guidance('Landscape photographs sit best in the grid.'),

                    TextInput::make('caption')
                        ->label('Caption')
                        ->maxLength(255)
                        ->helperText('Shown under the photograph. Optional.'),

                    /*
                     | Alt text and caption do different jobs, so they are
                     | separate fields. The caption is read by everyone; the alt
                     | text is read INSTEAD of the picture by someone using a
                     | screen reader. The model falls back to the caption when
                     | this is blank, because an imperfect alt beats none.
                     */
                    TextInput::make('alt_text')
                        ->label('Description for screen readers')
                        ->maxLength(255)
                        ->helperText('Describe what is in the photograph, for visitors who cannot see it. Leave empty to reuse the caption.'),

                    TextInput::make('sort_order')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first. You can also drag rows in the list.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->state(fn (GalleryImage $record): ?string => $record->imageUrl())
                    ->height(56)
                    ->width(84)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),

                TextColumn::make('caption')
                    ->searchable()
                    ->placeholder('No caption')
                    ->description(fn (GalleryImage $record): string => $record->altText())
                    ->wrap(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateHeading('No photographs yet')
            ->emptyStateDescription('Photographs of the chamber reassure patients who have not visited before.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGalleryImages::route('/'),
            'create' => CreateGalleryImage::route('/create'),
            'edit' => EditGalleryImage::route('/{record}/edit'),
        ];
    }
}
