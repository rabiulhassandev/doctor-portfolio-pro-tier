<?php

namespace App\Filament\Resources\SeoPages;

use App\Filament\Forms\Components\PhotoUpload;
use App\Filament\Resources\SeoPages\Pages\EditSeoPage;
use App\Filament\Resources\SeoPages\Pages\ListSeoPages;
use App\Models\DoctorProfile;
use App\Models\SeoPage;
use App\Models\SeoSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The search listing for each of the fixed pages.
 *
 * Deliberately NOT creatable or deletable. The rows are the site's own pages,
 * one per route, kept in step by {@see SeoPage::syncManagedPages()} when the
 * list opens. Offering a Create button here would mean asking a doctor to type
 * a Laravel route name into a text box, and the only two outcomes of that are a
 * duplicate row or a row that matches nothing.
 *
 * Articles and videos are not here. Each of those is a record the doctor
 * already edits, and its SEO fields live on its own form where the writing is —
 * splitting them across two screens would mean publishing an article in one
 * place and describing it in another.
 *
 * @see SeoPage
 */
class SeoPageResource extends Resource
{
    protected static ?string $model = SeoPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Search & visibility';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Page listings';

    protected static ?string $modelLabel = 'page listing';

    protected static ?string $pluralModelLabel = 'page listings';

    /** Rows are created by syncManagedPages(), never by hand. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('How this page appears in Google')
                ->description(fn (?SeoPage $record): string => $record
                    ? (SeoPage::MANAGED[$record->route_name]['hint'] ?? '')
                    : '')
                ->schema([
                    TextInput::make('title')
                        ->label('Page title')
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->helperText('Leave empty to use the page\'s own title. Aim for 50–60 characters — Google cuts the rest off.')
                        ->hint(fn (?string $state): string => mb_strlen((string) $state).'/60')
                        ->hintColor(fn (?string $state): string => match (true) {
                            blank($state) => 'gray',
                            mb_strlen($state) > 60 => 'warning',
                            default => 'success',
                        }),

                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->maxLength(320)
                        ->live(onBlur: true)
                        ->helperText('The grey text under the title. Leave empty to use your site-wide description.')
                        ->hint(fn (?string $state): string => mb_strlen((string) $state).'/160')
                        ->hintColor(fn (?string $state): string => match (true) {
                            blank($state) => 'gray',
                            mb_strlen($state) > 160 => 'warning',
                            mb_strlen($state) < 70 => 'warning',
                            default => 'success',
                        }),

                    /*
                     | A live mock-up of the Google result.
                     |
                     | This is the whole reason the screen is usable by somebody
                     | who is not an SEO consultant. "Write a meta description"
                     | is an abstract instruction; "here is what people will see"
                     | is a thing anyone can judge, and it makes the character
                     | limits self-evident without reading the helper text.
                     */
                    Text::make(fn (Get $get, ?SeoPage $record): HtmlString => self::searchPreview($get, $record)),
                ]),

            Section::make('Sharing')
                ->schema([
                    PhotoUpload::make('share_image')
                        ->label('Share image')
                        ->directory('seo')
                        ->helperText('Shown when this page is linked on WhatsApp or Facebook. Falls back to your site-wide image.'),
                ])
                ->collapsed(),

            Section::make('Advanced')
                ->description('Almost nobody needs to change these.')
                ->columns(2)
                ->collapsed()
                ->schema([
                    Toggle::make('noindex')
                        ->label('Hide this page from search engines')
                        ->helperText('The page stays on your website; it just stops appearing in results.'),

                    Toggle::make('nofollow')
                        ->label('Tell search engines not to follow links on this page'),

                    TextInput::make('canonical_url')
                        ->label('Canonical URL')
                        ->url()
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->helperText('Only if this page duplicates another one. Setting this wrong can remove the page from Google entirely — leave it empty unless you are certain.'),

                    Select::make('changefreq')
                        ->label('Sitemap: how often it changes')
                        ->options([
                            'daily' => 'Daily',
                            'weekly' => 'Weekly',
                            'monthly' => 'Monthly',
                            'yearly' => 'Yearly',
                        ])
                        ->placeholder('Use the default'),

                    TextInput::make('priority')
                        ->label('Sitemap: priority')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.1)
                        ->placeholder('Use the default')
                        ->helperText('0.0 to 1.0. Google largely ignores it.'),
                ]),
        ]);
    }

    /**
     * The Google-result mock-up shown under the title and description fields.
     *
     * Falls back exactly the way the real page does — page title, then site
     * name; page description, then the site default — so the preview is a
     * preview and not a second opinion.
     */
    private static function searchPreview(Get $get, ?SeoPage $record): HtmlString
    {
        $doctor = DoctorProfile::current();
        $settings = SeoSetting::current();
        $siteName = $doctor->name ?: config('site.name');

        $pageTitle = $get('title') ?: ($record ? $record->label() : null);
        $title = $settings->buildTitle($pageTitle, $siteName);

        $description = $get('description')
            ?: $settings->default_meta_description
            ?: $doctor->meta_description
            ?: $doctor->short_bio
            ?: config('site.meta_description');

        $url = $record?->url() ?? url('/');

        return new HtmlString(
            '<div style="max-width:37rem;padding:1rem 1.15rem;border:1px solid rgb(0 0 0 / 0.08);border-radius:.5rem;background:#fff;font-family:arial,sans-serif">'
            .'<div style="font-size:.75rem;color:#4d5156;margin-bottom:.15rem">'.e($siteName).'</div>'
            .'<div style="font-size:.75rem;color:#4d5156;margin-bottom:.35rem">'.e($url).'</div>'
            .'<div style="font-size:1.25rem;line-height:1.3;color:#1a0dab;margin-bottom:.25rem">'.e(Str::limit($title, 60)).'</div>'
            .'<div style="font-size:.875rem;line-height:1.58;color:#4d5156">'.e(Str::limit(strip_tags((string) $description), 160)).'</div>'
            .'</div>'
        );
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('route_name')
                    ->label('Page')
                    ->formatStateUsing(fn (SeoPage $record): string => $record->label())
                    ->description(fn (SeoPage $record): ?string => $record->url())
                    ->weight('semibold'),

                /*
                 | "Not set" is the important state on this screen, and it has
                 | to be visible at a glance across the whole list — the value
                 | of the page is telling a doctor which of their pages they
                 | have not written a description for yet.
                 */
                TextColumn::make('title')
                    ->label('Title')
                    ->placeholder('Using the page default')
                    ->limit(45)
                    ->color(fn (?string $state): string => blank($state) ? 'gray' : 'default'),

                TextColumn::make('description')
                    ->label('Description')
                    ->placeholder('Using the site default')
                    ->limit(50)
                    ->color(fn (?string $state): string => blank($state) ? 'gray' : 'default')
                    ->toggleable(),

                IconColumn::make('noindex')
                    ->label('Hidden')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye-slash')
                    ->falseIcon('heroicon-o-eye')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn (SeoPage $record): string => $record->noindex
                        ? 'Hidden from search engines'
                        : 'Visible to search engines'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            // No bulk actions: there is nothing sensible to do to several of
            // these at once, and the only destructive one is disabled anyway.
            ->toolbarActions([])
            ->emptyStateHeading('No pages found')
            ->emptyStateDescription('This list fills itself from your website\'s pages. If it is empty, reload the screen.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoPages::route('/'),
            'edit' => EditSeoPage::route('/{record}/edit'),
        ];
    }
}
