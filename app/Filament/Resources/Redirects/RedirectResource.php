<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Http\Controllers\RedirectController;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Old addresses that should send visitors somewhere on the new site.
 *
 * The case this exists for: a practice replacing an existing website. Whatever
 * position their old pages hold in Google is attached to the old URLs, and a
 * launch that answers 404 on every one of them throws it away — silently, and
 * usually undetected for weeks, because the new site looks perfect.
 *
 * @see Redirect
 * @see RedirectController
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Search & visibility';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Redirects';

    protected static ?string $modelLabel = 'redirect';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('What this is for')
                ->info()
                ->schema([
                    Text::make('If you had a website before this one, its pages have addresses that people and Google still remember. A redirect sends anyone who follows an old link to the right page here, instead of showing them an error.'),
                ]),

            Section::make()
                ->schema([
                    TextInput::make('from_path')
                        ->label('Old address')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('/our-services')
                        ->helperText('The part after your domain name. You can paste the whole old address — anything before the first slash is ignored.')
                        /*
                         | Normalised as it is typed, not only on save.
                         |
                         | The model normalises in a saving hook, but the unique
                         | rule runs before that: "/services/" and "/services"
                         | would both pass validation and then collide on the
                         | database index, which surfaces as a raw SQL error
                         | rather than a form message. Doing it on blur means
                         | the doctor also SEES what was stored.
                         */
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('from_path', Redirect::normalisePath($state)))
                        ->unique(ignoreRecord: true)
                        ->rule('not_regex:/^\\/admin/')
                        ->validationMessages([
                            'unique' => 'There is already a redirect for that address.',
                            'not_regex' => 'The admin panel cannot be redirected.',
                        ]),

                    TextInput::make('to_path')
                        ->label('Send them to')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('/services')
                        ->helperText('A page on this site, such as /services, or a full address somewhere else.'),

                    Select::make('status_code')
                        ->label('Type')
                        ->required()
                        ->default(301)
                        ->options([
                            301 => 'Permanent — the page has moved for good (recommended)',
                            302 => 'Temporary — it will come back',
                        ])
                        ->helperText('Permanent passes the old page\'s standing in Google to the new one. Use temporary only if you mean it.'),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->helperText('Switch off to stop the redirect without deleting it.'),

                    TextInput::make('note')
                        ->label('Note (optional)')
                        ->maxLength(255)
                        ->placeholder('Old services page from the 2019 site'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('hits', 'desc')
            ->columns([
                TextColumn::make('from_path')
                    ->label('Old address')
                    ->searchable()
                    ->copyable()
                    ->weight('semibold')
                    ->description(fn (Redirect $record): ?string => $record->note),

                TextColumn::make('to_path')
                    ->label('Goes to')
                    ->searchable()
                    ->url(fn (Redirect $record): string => $record->target(), shouldOpenInNewTab: true)
                    ->color('primary'),

                TextColumn::make('status_code')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (int $state): string => $state === 301 ? 'Permanent' : 'Temporary')
                    ->color(fn (int $state): string => $state === 301 ? 'success' : 'warning'),

                /*
                 | The column that makes the screen worth having. A rule nobody
                 | has followed in a year can be deleted; one with four thousand
                 | hits is load-bearing and must not be. Without this, every
                 | redirect looks equally disposable.
                 */
                TextColumn::make('hits')
                    ->label('Times used')
                    ->numeric()
                    ->sortable()
                    ->description(fn (Redirect $record): string => $record->last_hit_at
                        ? 'Last '.$record->last_hit_at->diffForHumans()
                        : 'Never used'),

                IconColumn::make('is_active')
                    ->label('On')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-arrows-right-left')
            ->emptyStateHeading('No redirects')
            ->emptyStateDescription('If this practice had a website before, add its old page addresses here so those links keep working.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
