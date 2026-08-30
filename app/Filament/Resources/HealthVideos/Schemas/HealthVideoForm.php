<?php

namespace App\Filament\Resources\HealthVideos\Schemas;

use App\Enums\VideoType;
use App\Filament\Forms\Components\PhotoUpload;
use App\Models\HealthVideo;
use App\Support\VideoEmbed;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class HealthVideoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The video')
                ->schema([
                    Radio::make('video_type')
                        ->label('Where is the video?')
                        ->options(VideoType::class)
                        ->default(VideoType::Youtube->value)
                        ->required()
                        ->live()
                        ->inline(false)
                        ->helperText('A YouTube or Vimeo link is strongly recommended — they handle the streaming, so your site stays fast.'),

                    TextInput::make('source_url')
                        ->label('Video link')
                        ->required(fn (Get $get): bool => $get('video_type') !== VideoType::Upload->value)
                        ->visible(fn (Get $get): bool => $get('video_type') !== VideoType::Upload->value)
                        ->maxLength(500)
                        ->placeholder('https://www.youtube.com/watch?v=…')
                        ->helperText('Paste the address straight from your browser or the Share button. Any YouTube or Vimeo form works.')
                        /*
                         | Validating here means the doctor is told at paste
                         | time, rather than finding a blank player a week
                         | later. The model normalises the same URL on save.
                         */
                        ->rule(fn (): callable => function (string $attribute, $value, callable $fail): void {
                            if (filled($value) && VideoEmbed::parse($value) === null) {
                                $fail('That does not look like a YouTube or Vimeo address. Copy the link from your browser and try again.');
                            }
                        }),

                    FileUpload::make('video_path')
                        ->label('Video file')
                        ->required(fn (Get $get): bool => $get('video_type') === VideoType::Upload->value)
                        ->visible(fn (Get $get): bool => $get('video_type') === VideoType::Upload->value)
                        ->disk('public')
                        ->directory('videos')
                        ->acceptedFileTypes(['video/mp4', 'video/webm'])
                        ->maxSize(51200)
                        /*
                         | 50 MB is already optimistic on shared hosting: PHP's
                         | upload_max_filesize and post_max_size usually cap out
                         | well below it, and raising them is the buyer's job.
                         | The README says so.
                         */
                        ->helperText('MP4 or WebM, up to 50 MB. Your hosting may allow less — see the README. YouTube is the better route for anything longer than a minute or two.'),
                ]),

            Section::make('About this video')
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
                        ->prefix(url('/health-videos').'/')
                        ->helperText('Avoid changing this after publishing — old links would stop working.'),

                    TextInput::make('topic')
                        ->label('Condition or topic')
                        ->maxLength(255)
                        ->datalist(fn (): array => HealthVideo::query()
                            ->whereNotNull('topic')
                            ->distinct()
                            ->pluck('topic')
                            ->all())
                        ->placeholder('Heart failure')
                        ->helperText('Patients filter the library by this. Reuse the same wording so videos group together.'),

                    Textarea::make('description')
                        ->label('What is it about?')
                        ->rows(4)
                        ->columnSpanFull()
                        ->helperText('Shown under the player. A few sentences is plenty.'),

                    PhotoUpload::make('thumbnail_path')
                        ->label('Thumbnail')
                        ->directory('videos/thumbnails')
                        ->imageEditorAspectRatioOptions([null, '16:9'])
                        ->guidance('Optional for YouTube and Vimeo — we fetch theirs automatically. Needed for uploaded files.'),

                    TextInput::make('duration_seconds')
                        ->label('Length')
                        ->numeric()
                        ->suffix('seconds')
                        ->minValue(1)
                        ->helperText('Optional. Shown on the card, and helps Google understand the video.'),
                ]),

            Section::make('Publishing')
                ->columns(3)
                ->schema([
                    Toggle::make('is_published')
                        ->label('Published')
                        ->default(true)
                        ->helperText('Turn off to hide it from the website.'),

                    Toggle::make('is_featured')
                        ->label('Show on the home page')
                        ->helperText('A few featured videos appear on the home page.'),

                    DateTimePicker::make('published_at')
                        ->label('Publish date')
                        ->seconds(false)
                        ->default(now())
                        ->helperText('Set a future date to schedule it.'),

                    TextInput::make('sort_order')
                        ->label('Order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first.'),
                ]),

            Section::make('Search engine listing (optional)')
                ->description('Leave these blank to reuse the title and description above.')
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
}
