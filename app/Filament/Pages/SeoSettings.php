<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\PhotoUpload;
use App\Models\DoctorProfile;
use App\Models\SeoSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Site-wide search settings.
 *
 * A singleton page rather than a resource, the same shape as
 * {@see DoctorProfileSettings}: one row, nothing to browse, nothing to create.
 *
 * The organising idea behind the tabs is that a doctor is not an SEO
 * consultant. Each one answers a question they would actually ask —
 * "how do I appear in Google", "should I let ChatGPT read this", "how do I
 * prove I own the domain" — rather than grouping fields by which HTML tag they
 * end up in. Nearly every field has helper text saying what it does and what
 * happens if it is left empty, because the alternative is a doctor guessing.
 *
 * @see SeoSetting
 */
class SeoSettings extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Search & visibility';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'SEO settings';

    protected static ?string $title = 'SEO settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'How your website appears in Google, and what AI assistants such as ChatGPT and Gemini are allowed to read.';
    }

    public function mount(): void
    {
        $settings = SeoSetting::query()->first();

        $this->form->fill(
            $settings?->attributesToArray() ?? [
                'title_template' => ':page | :site',
                'default_meta_description' => DoctorProfile::current()->meta_description,
                // Everything allowed until somebody decides otherwise. See the
                // note on SeoSetting::allowsCrawler().
                'ai_crawlers' => collect(SeoSetting::AI_CRAWLERS)
                    ->map(fn (): bool => true)
                    ->all(),
            ]
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->tabs([
                    self::listingTab(),
                    self::indexingTab(),
                    self::aiTab(),
                    self::verificationTab(),
                    self::analyticsTab(),
                    self::schemaTab(),
                ])->persistTabInQueryString(),
            ]);
    }

    /** Renders the form with a sticky Save button underneath. */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Save changes')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SeoSetting::query()->updateOrCreate(
            ['id' => SeoSetting::query()->value('id')],
            $data,
        );

        /*
         | The staging switch is worth a second, louder message. Somebody who
         | leaves it on has an invisible website and no error anywhere to tell
         | them why — it is the single most expensive mistake this screen makes
         | possible, so it gets a persistent warning rather than a toast that
         | disappears in four seconds.
         */
        if ($data['discourage_indexing'] ?? false) {
            Notification::make()
                ->warning()
                ->persistent()
                ->title('Your site is hidden from search engines')
                ->body('“Ask search engines not to index this site” is switched on. Turn it off when you are ready to be found.')
                ->send();
        }

        Notification::make()
            ->success()
            ->title('SEO settings saved')
            ->send();
    }

    // -----------------------------------------------------------------------
    // Tabs
    // -----------------------------------------------------------------------

    private static function listingTab(): Tab
    {
        return Tab::make('Search listing')
            ->icon('heroicon-o-magnifying-glass')
            ->schema([
                Section::make('Page titles')
                    ->description('The blue line in a Google result, and the text in a browser tab.')
                    ->schema([
                        TextInput::make('title_template')
                            ->label('Title format')
                            ->required()
                            ->maxLength(255)
                            ->default(':page | :site')
                            ->helperText(new HtmlString(
                                'Use <code>:page</code> for the page name and <code>:site</code> for your name. '
                                .'The default gives titles like <strong>Book an appointment | Dr. Tahmina Rahman</strong>. '
                                .'Your home page always uses your name on its own.'
                            ))
                            ->live(onBlur: true),

                        /*
                         | A worked example of the template, updating as it is
                         | typed. A format string with two placeholders is the
                         | sort of field people get subtly wrong and never
                         | check — showing the result removes the guesswork
                         | entirely.
                         */
                        Text::make(fn (Get $get): string => 'Preview: '.SeoSetting::make([
                            'title_template' => $get('title_template') ?: ':page | :site',
                        ])->buildTitle('Book an appointment', DoctorProfile::current()->name ?: config('site.name')))
                            ->color('gray'),
                    ]),

                Section::make('Default description')
                    ->description('The grey text under the title in a search result. Individual pages can override it.')
                    ->schema([
                        Textarea::make('default_meta_description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(320)
                            ->live(onBlur: true)
                            ->helperText('Google shows roughly 150–160 characters. Write it for a person deciding whether to click, not for a search engine.')
                            ->hint(fn (?string $state): string => mb_strlen((string) $state).' characters')
                            ->hintColor(fn (?string $state): string => match (true) {
                                blank($state) => 'gray',
                                mb_strlen($state) < 70 => 'warning',
                                mb_strlen($state) > 160 => 'warning',
                                default => 'success',
                            }),
                    ]),

                Section::make('Sharing')
                    ->description('What appears when somebody sends a link to your site on WhatsApp, Facebook or X.')
                    ->columns(2)
                    ->schema([
                        PhotoUpload::make('default_share_image')
                            ->label('Default share image')
                            ->directory('seo')
                            ->helperText('1200 × 630 pixels works everywhere. Falls back to your portrait if empty.')
                            ->columnSpanFull(),

                        TextInput::make('twitter_handle')
                            ->label('X (Twitter) username')
                            ->prefix('@')
                            ->maxLength(64)
                            ->helperText('Without the @. Leave empty if you do not have one.'),
                    ]),
            ]);
    }

    private static function indexingTab(): Tab
    {
        return Tab::make('Indexing')
            ->icon('heroicon-o-eye')
            ->schema([
                Section::make('Visibility')
                    ->schema([
                        Toggle::make('discourage_indexing')
                            ->label('Ask search engines not to index this site')
                            ->live()
                            ->helperText('For a site that is still being built. Turn it OFF before you launch.'),

                        /*
                         | Shown only while the switch is on, and worded as
                         | plainly as it can be. This is the field that quietly
                         | costs a practice its entire search presence, so it
                         | gets a full callout rather than a line of grey text.
                         */
                        Callout::make('Your website is hidden from search engines')
                            ->danger()
                            ->schema([
                                Text::make('Nobody searching for you on Google will find this site until the switch above is turned off.'),
                            ])
                            ->visible(fn (Get $get): bool => (bool) $get('discourage_indexing')),
                    ]),

                Section::make('robots.txt')
                    ->description('The file that tells crawlers where they may go. This site generates it for you at /robots.txt — anything below is added to the end.')
                    ->schema([
                        Textarea::make('robots_extra')
                            ->label('Extra rules')
                            ->rows(5)
                            ->helperText('Advanced. One directive per line, for example “Disallow: /private-page”. Leave empty unless you know you need it — the generated file already blocks the admin panel, the patient area and payment callbacks.')
                            ->extraInputAttributes(['class' => 'font-mono text-sm']),
                    ]),
            ]);
    }

    private static function aiTab(): Tab
    {
        $groups = collect(SeoSetting::AI_CRAWLERS)->groupBy('kind');

        return Tab::make('AI assistants')
            ->icon('heroicon-o-sparkles')
            ->schema([
                Section::make('Being cited in AI answers')
                    ->description('These crawlers fetch your pages so an assistant can quote you and link back. Blocking them is like blocking Google — people asking ChatGPT for a cardiologist in Dhanmondi will not find you.')
                    ->columns(2)
                    ->schema(
                        $groups->get('search', collect())
                            ->map(fn (array $bot, string $agent): Toggle => Toggle::make("ai_crawlers.{$agent}")
                                ->label($bot['label'])
                                ->helperText($bot['vendor'].' · '.$agent)
                                ->default(true))
                            ->values()
                            ->all()
                    ),

                Section::make('Model training')
                    ->description('These collect text to train AI models. It does not affect whether you appear in answers, so this is a question about how you feel rather than an SEO decision.')
                    ->columns(2)
                    ->schema(
                        $groups->get('training', collect())
                            ->map(fn (array $bot, string $agent): Toggle => Toggle::make("ai_crawlers.{$agent}")
                                ->label($bot['label'])
                                ->helperText($bot['vendor'].' · '.$agent)
                                ->default(true))
                            ->values()
                            ->all()
                    ),

                Section::make('llms.txt')
                    ->description('A plain summary of your site, served at /llms.txt, for AI assistants that look for one.')
                    ->collapsed()
                    ->schema([
                        Textarea::make('llms_txt')
                            ->label('Summary')
                            ->rows(12)
                            ->helperText('Leave empty and one is generated from your profile, services and articles — which is the right choice for almost everyone. Markdown.')
                            ->extraInputAttributes(['class' => 'font-mono text-sm']),

                        Text::make('llms.txt is a proposed convention, not a standard, and the major assistants do not commit to reading it. It costs nothing to publish and may help; do not expect it to do the work on its own.')
                            ->color('gray'),
                    ]),
            ]);
    }

    private static function verificationTab(): Tab
    {
        return Tab::make('Verification')
            ->icon('heroicon-o-check-badge')
            ->schema([
                Section::make('Prove you own this website')
                    ->description('Each search engine gives you a code when you add your site to its tools. Paste it here and the tag is added to every page.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('google_verification')
                            ->label('Google Search Console')
                            ->maxLength(255)
                            ->helperText('Search Console → Settings → Ownership verification → HTML tag. Paste only the content value.'),

                        TextInput::make('bing_verification')
                            ->label('Bing Webmaster Tools')
                            ->maxLength(255),

                        TextInput::make('yandex_verification')
                            ->label('Yandex')
                            ->maxLength(255),

                        TextInput::make('pinterest_verification')
                            ->label('Pinterest')
                            ->maxLength(255),
                    ]),

                Section::make('Where to submit your sitemap')
                    ->schema([
                        Text::make(fn (): string => 'Your sitemap is generated automatically and always current: '.route('sitemap')),
                        Text::make('Submit that address in Google Search Console and Bing Webmaster Tools once. You never need to touch it again — new articles and videos appear in it the moment you publish them.')
                            ->color('gray'),
                    ]),
            ]);
    }

    private static function analyticsTab(): Tab
    {
        return Tab::make('Analytics')
            ->icon('heroicon-o-chart-bar')
            ->schema([
                Callout::make('Before you fill this in')
                    ->warning()
                    ->schema([
                        Text::make('This is a medical website. The pages somebody visits here say what they are worried might be wrong with them, and anything you add below sends that to another company.'),
                        Text::make('This site never loads tracking on the booking pages or inside a patient account, whatever you put here. Everything else is your decision, and it may be regulated where you practise. Leaving all of it empty is a perfectly good answer.')
                            ->color('gray'),
                    ]),

                Section::make('Tags')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ga4_measurement_id')
                            ->label('Google Analytics 4')
                            ->placeholder('G-XXXXXXXXXX')
                            ->maxLength(32)
                            ->rule('regex:/^G-[A-Z0-9]{6,}$/i')
                            ->validationMessages(['regex' => 'A GA4 ID looks like G-XXXXXXXXXX.'])
                            ->helperText('Google Analytics → Admin → Data streams.'),

                        TextInput::make('gtm_container_id')
                            ->label('Google Tag Manager')
                            ->placeholder('GTM-XXXXXXX')
                            ->maxLength(32)
                            ->rule('regex:/^GTM-[A-Z0-9]+$/i')
                            ->validationMessages(['regex' => 'A GTM container looks like GTM-XXXXXXX.']),

                        TextInput::make('meta_pixel_id')
                            ->label('Meta (Facebook) pixel')
                            ->placeholder('123456789012345')
                            ->maxLength(32)
                            ->rule('regex:/^[0-9]+$/')
                            ->validationMessages(['regex' => 'A Meta pixel ID is digits only.']),
                    ]),

                Section::make('Custom code')
                    ->description('For a tag this panel does not have a box for.')
                    ->collapsed()
                    ->schema([
                        Textarea::make('head_scripts')
                            ->label('Before </head>')
                            ->rows(4)
                            ->extraInputAttributes(['class' => 'font-mono text-sm'])
                            ->helperText('Added to every public page exactly as typed.'),

                        Textarea::make('body_scripts')
                            ->label('Before </body>')
                            ->rows(4)
                            ->extraInputAttributes(['class' => 'font-mono text-sm']),

                        Callout::make('This code is not checked')
                            ->danger()
                            ->schema([
                                Text::make('Anything here runs in your visitors’ browsers exactly as typed. Paste only code you got from a company you trust, and never code somebody sent you.'),
                            ]),
                    ]),
            ]);
    }

    private static function schemaTab(): Tab
    {
        return Tab::make('Business details')
            ->icon('heroicon-o-building-storefront')
            ->schema([
                Section::make('Extra facts for search engines and AI')
                    ->description('These are published as structured data — the machine-readable summary Google uses for its local panel, and the part an AI assistant reads when somebody asks it to recommend a doctor. Your name, address, hours and fee already come from your profile.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('legal_name')
                            ->label('Registered practice name')
                            ->maxLength(255)
                            ->helperText('Only if it differs from your chamber name.'),

                        TextInput::make('founding_year')
                            ->label('Practising since')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue((int) date('Y'))
                            ->helperText('The year this chamber opened.'),

                        TextInput::make('price_range')
                            ->label('Price range')
                            ->maxLength(32)
                            ->placeholder('৳৳')
                            ->helperText('Google shows this in its local panel. Symbols or a range both work.'),

                        TagsInput::make('languages')
                            ->label('Languages you consult in')
                            ->placeholder('Add a language')
                            ->helperText('Worth filling in. “A doctor who speaks Bangla and English” is exactly the kind of question people ask an assistant.'),

                        TagsInput::make('areas_served')
                            ->label('Areas you serve')
                            ->placeholder('Add an area')
                            ->helperText('Dhanmondi, Dhaka, Bangladesh — from most specific to least.'),

                        TagsInput::make('payment_accepted')
                            ->label('Payment accepted')
                            ->placeholder('Add a method')
                            ->helperText('Cash, bKash, card, online.'),
                    ]),
            ]);
    }
}
