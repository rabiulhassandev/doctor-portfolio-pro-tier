<?php

namespace App\Filament\Resources\HealthVideos;

use App\Filament\Resources\HealthVideos\Pages\CreateHealthVideo;
use App\Filament\Resources\HealthVideos\Pages\EditHealthVideo;
use App\Filament\Resources\HealthVideos\Pages\ListHealthVideos;
use App\Filament\Resources\HealthVideos\Schemas\HealthVideoForm;
use App\Filament\Resources\HealthVideos\Tables\HealthVideosTable;
use App\Models\HealthVideo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The patient education library.
 */
class HealthVideoResource extends Resource
{
    protected static ?string $model = HealthVideo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlayCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Website content';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Health videos';

    protected static ?string $modelLabel = 'health video';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return HealthVideoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HealthVideosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHealthVideos::route('/'),
            'create' => CreateHealthVideo::route('/create'),
            'edit' => EditHealthVideo::route('/{record}/edit'),
        ];
    }
}
