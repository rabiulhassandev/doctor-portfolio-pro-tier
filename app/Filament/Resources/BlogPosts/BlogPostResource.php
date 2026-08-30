<?php

namespace App\Filament\Resources\BlogPosts;

use App\Filament\Forms\Components\PhotoUpload;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Articles the doctor writes for patients.
 */
class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Website content';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Articles';

    protected static ?string $modelLabel = 'article';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Article')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, $set): void {
                            // Only while creating — changing it later would
                            // break links people have already shared.
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),

                    TextInput::make('slug')
                        ->label('Web address')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->prefix(url('/blog').'/')
                        ->helperText('Avoid changing this after publishing — old links would stop working.'),

                    PhotoUpload::make('cover_image')
                        ->label('Cover image')
                        ->directory('blog')
                        ->guidance('Wide images work best, roughly 1200 × 630 pixels.'),

                    Textarea::make('excerpt')
                        ->label('Short summary')
                        ->rows(3)
                        ->maxLength(500)
                        ->columnSpanFull()
                        ->helperText('Shown on the blog list and in search results. Leave empty to use the opening lines.'),

                    RichEditor::make('content')
                        ->label('Article body')
                        ->required()
                        ->columnSpanFull()
                        // Pasted images land on the public disk with the rest
                        // of the uploads.
                        ->fileAttachmentsDirectory('blog/inline')
                        ->fileAttachmentsVisibility('public'),
                ]),

            Section::make('Publishing')
                ->description('An article goes live only when it is switched on AND its publish date has passed.')
                ->columns(2)
                ->schema([
                    Toggle::make('is_published')
                        ->label('Published')
                        ->helperText('Leave off to keep working on a draft.'),

                    DateTimePicker::make('published_at')
                        ->label('Publish date')
                        ->seconds(false)
                        ->default(now())
                        ->helperText('Set a future date to schedule the article.'),
                ]),

            Section::make('Search engine listing (optional)')
                ->description('Leave blank to reuse the title and summary above.')
                ->collapsed()
                ->schema([
                    TextInput::make('meta_title')
                        ->label('Meta title')
                        ->maxLength(255),

                    Textarea::make('meta_description')
                        ->label('Meta description')
                        ->rows(2)
                        ->maxLength(500),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image')
                    ->label('')
                    ->state(fn (BlogPost $record): ?string => $record->coverUrl())
                    ->height(44)
                    ->width(78)
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->description(fn (BlogPost $record): string => $record->readingMinutes().' min read')
                    ->weight('semibold')
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(function (BlogPost $record): string {
                        if (! $record->is_published) {
                            return 'Draft';
                        }

                        return $record->published_at?->isFuture() ? 'Scheduled' : 'Live';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Live' => 'success',
                        'Scheduled' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('published_at')
                    ->label('Date')
                    ->date('j M Y')
                    ->sortable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('is_published')
                    ->label('Published')
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
            ->emptyStateIcon('heroicon-o-newspaper')
            ->emptyStateHeading('No articles yet')
            ->emptyStateDescription('Writing about the conditions you treat is the single best way for patients to find you in search.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogPosts::route('/'),
            'create' => CreateBlogPost::route('/create'),
            'edit' => EditBlogPost::route('/{record}/edit'),
        ];
    }
}
