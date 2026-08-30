<?php

namespace App\Filament\Resources\HealthVideos\Tables;

use App\Enums\VideoType;
use App\Models\HealthVideo;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HealthVideosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Dragging a row writes straight to sort_order, which is the order
            // the public library uses.
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('')
                    ->state(fn (HealthVideo $record): ?string => $record->thumbnailUrl())
                    ->height(44)
                    ->width(78)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (HealthVideo $record): ?string => $record->topic)
                    ->weight('semibold')
                    ->wrap(),

                TextColumn::make('video_type')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (VideoType $state): string => match ($state) {
                        VideoType::Upload => 'Uploaded',
                        VideoType::Youtube => 'YouTube',
                        VideoType::Vimeo => 'Vimeo',
                    })
                    ->color(fn (VideoType $state): string => $state === VideoType::Upload ? 'warning' : 'info'),

                TextColumn::make('duration_seconds')
                    ->label('Length')
                    ->state(fn (HealthVideo $record): string => $record->formattedDuration() ?? '—')
                    ->alignCenter(),

                IconColumn::make('is_featured')
                    ->label('Home page')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_published')
                    ->label('Live')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('topic')
                    ->label('Topic')
                    ->options(fn (): array => HealthVideo::query()
                        ->whereNotNull('topic')
                        ->distinct()
                        ->orderBy('topic')
                        ->pluck('topic', 'topic')
                        ->all()),

                SelectFilter::make('video_type')
                    ->label('Source')
                    ->options(VideoType::class),

                SelectFilter::make('is_published')
                    ->label('Live')
                    ->options([1 => 'Published', 0 => 'Draft']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-play-circle')
            ->emptyStateHeading('No videos yet')
            ->emptyStateDescription('Add short videos explaining the conditions you treat. Patients watch these instead of telephoning to ask.');
    }
}
