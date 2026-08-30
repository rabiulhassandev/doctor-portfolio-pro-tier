<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Resources\Faqs\Pages\EditFaq;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The questions the chamber answers on the phone forty times a week.
 *
 * These are also published as schema.org FAQPage markup, which is what lets
 * Google show the answers directly in search results — the highest-value piece
 * of structured data on a site like this.
 */
class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Website content';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Common questions';

    protected static ?string $modelLabel = 'question';

    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Question and answer')
                ->schema([
                    TextInput::make('question')
                        ->required()
                        ->maxLength(500)
                        ->placeholder('Do I need an appointment, or can I just come?')
                        ->helperText('Write it the way a patient would ask it on the phone.'),

                    /*
                     | Plain text, not rich text, on purpose. Google's FAQPage
                     | markup accepts only limited HTML and quietly rejects
                     | answers pasted in with headings and images — so what the
                     | doctor types here is what actually gets indexed.
                     */
                    Textarea::make('answer')
                        ->required()
                        ->rows(5)
                        ->helperText('Keep it plain — no headings or images. Google shows these answers directly in search results.'),

                    TextInput::make('category')
                        ->label('Group (optional)')
                        ->maxLength(255)
                        ->datalist(fn (): array => Faq::query()
                            ->whereNotNull('category')
                            ->distinct()
                            ->pluck('category')
                            ->all())
                        ->placeholder('Appointments')
                        ->helperText('Questions with the same group appear together. Leave empty and it goes in the general list.'),
                ]),

            Section::make('Display')
                ->columns(2)
                ->schema([
                    Toggle::make('is_published')
                        ->label('Published')
                        ->default(true),

                    TextInput::make('sort_order')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first. Put the most-asked question at the top.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('question')
                    ->searchable()
                    ->description(fn (Faq $record): string => str($record->answer)->limit(90)->toString())
                    ->weight('semibold')
                    ->wrap(),

                TextColumn::make('category')
                    ->label('Group')
                    ->badge()
                    ->placeholder('General')
                    ->toggleable(),

                IconColumn::make('is_published')
                    ->label('Live')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Group')
                    ->options(fn (): array => Faq::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-question-mark-circle')
            ->emptyStateHeading('No questions yet')
            ->emptyStateDescription('Add the questions your staff answer on the telephone most often. They save calls, and Google shows them in search results.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
        ];
    }
}
