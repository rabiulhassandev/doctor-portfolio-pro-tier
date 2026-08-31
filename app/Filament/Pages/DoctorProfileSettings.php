<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\PhotoUpload;
use App\Models\DoctorProfile;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The doctor's own details — the single most important screen in the panel.
 *
 * DoctorProfile is a singleton (one row, ever), so this is a plain Filament page
 * with a form rather than a resource: there is no list to browse and nothing to
 * create or delete.
 *
 * @see DoctorProfile
 */
class DoctorProfileSettings extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Website content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Doctor profile';

    protected static ?string $title = 'Doctor profile';

    /**
     * Live form state. Filament writes every field into this array; save() then
     * pushes the whole array onto the single database row.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function getSubheading(): ?string
    {
        return 'Your name, photo, contact details, fee and opening hours. These appear all over the website.';
    }

    public function mount(): void
    {
        // On a brand-new install there is no row yet, so fall back to sensible
        // defaults rather than showing a blank Monday-to-Sunday table.
        $profile = DoctorProfile::query()->first();

        $this->form->fill(
            $profile?->attributesToArray() ?? [
                'name' => config('site.name'),
                'specialization' => config('site.specialization'),
                'working_hours' => DoctorProfile::defaultWorkingHours(),
            ]
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->tabs([
                    self::identityTab(),
                    self::aboutTab(),
                    self::contactTab(),
                    self::hoursTab(),
                    self::bookingTab(),
                    self::onlineTab(),
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

    /** Write the form back to the one and only profile row. */
    public function save(): void
    {
        $data = $this->form->getState();

        // The model clears its own cached copy on save, so the public site picks
        // up the change on the next page load with nothing further to do here.
        DoctorProfile::query()->updateOrCreate(
            ['id' => DoctorProfile::query()->value('id')],
            $data,
        );

        Notification::make()
            ->success()
            ->title('Profile saved')
            ->body('Your website has been updated.')
            ->send();
    }

    // -----------------------------------------------------------------------
    // Tabs
    // -----------------------------------------------------------------------

    private static function identityTab(): Tab
    {
        return Tab::make('Basics')
            ->icon('heroicon-o-identification')
            ->schema([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Full name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Dr. Amelia Hart'),

                        TextInput::make('specialization')
                            ->label('Specialisation')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Consultant Cardiologist'),

                        TextInput::make('chamber_name')
                            ->label('Chamber name')
                            ->maxLength(255)
                            ->placeholder('Anwara Heart Care')
                            ->helperText('The name on the sign outside. Leave empty to show only your own name.'),

                        // Two fields rather than one so the register can differ
                        // by country without the label being baked into the data.
                        TextInput::make('registration_label')
                            ->label('Registration body')
                            ->maxLength(255)
                            ->placeholder('BMDC Reg. No.')
                            ->helperText('Shown in front of the number below.'),

                        TextInput::make('registration_number')
                            ->label('Registration number')
                            ->maxLength(255)
                            ->placeholder('A-42817')
                            ->helperText('Patients check this. Leave both fields empty to hide it entirely.'),

                        TextInput::make('tagline')
                            ->label('Tagline')
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->helperText('One line under your name on the home page.')
                            ->placeholder('Compassionate heart care, backed by twenty years of practice.'),

                        TextInput::make('years_of_experience')
                            ->label('Years of experience')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(80)
                            ->helperText('Leave empty to keep this off the website.'),

                        PhotoUpload::make('photo')
                            ->label('Your photo')
                            ->directory('doctor')
                            // The hero crops to a tall panel, so offering the
                            // matching ratio saves a round of trial and error.
                            ->imageEditorAspectRatioOptions([null, '4:5', '3:4', '1:1'])
                            ->guidance('A clear portrait, shown large on the home page.'),
                    ]),
            ]);
    }

    private static function aboutTab(): Tab
    {
        return Tab::make('About you')
            ->icon('heroicon-o-document-text')
            ->schema([
                Section::make('Your story')
                    ->schema([
                        Textarea::make('short_bio')
                            ->label('Short introduction')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Two or three sentences, shown on the home page.'),

                        Textarea::make('bio')
                            ->label('Full biography')
                            ->rows(10)
                            ->helperText('The main text on the About page. Leave a blank line between paragraphs.'),

                        Textarea::make('philosophy')
                            ->label('Your approach to care')
                            ->rows(6)
                            ->helperText('Optional. How you like to work with patients.'),
                    ]),

                Section::make('Qualifications')
                    ->description('Listed on the About page. Newest first works well.')
                    ->schema([
                        Repeater::make('qualifications')
                            ->hiddenLabel()
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Add a qualification')
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->collapsible()
                            ->schema([
                                TextInput::make('title')
                                    ->label('Qualification')
                                    ->required()
                                    ->placeholder('MBBS'),

                                TextInput::make('institution')
                                    ->label('Institution')
                                    ->placeholder('Dhaka Medical College'),

                                TextInput::make('year')
                                    ->label('Year')
                                    ->placeholder('2005')
                                    ->maxLength(9),
                            ]),
                    ]),
            ]);
    }

    private static function contactTab(): Tab
    {
        return Tab::make('Contact & chamber')
            ->icon('heroicon-o-map-pin')
            ->schema([
                Section::make('How patients reach you')
                    ->columns(3)
                    ->schema([
                        TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(255)
                            ->helperText('Becomes a tap-to-call link on mobile.'),

                        TextInput::make('whatsapp')
                            ->label('WhatsApp number')
                            ->maxLength(255)
                            ->placeholder('8801712345678')
                            ->helperText('Country code and number, digits only. Leave empty to hide the WhatsApp button.'),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Booking notifications are sent here.'),
                    ]),

                Section::make('Chamber address')
                    ->columns(2)
                    ->schema([
                        TextInput::make('address_line')
                            ->label('Street address')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('city')->label('City')->maxLength(255),
                        TextInput::make('state')->label('District or state')->maxLength(255),
                        TextInput::make('postal_code')->label('Postcode')->maxLength(255),
                        TextInput::make('country')->label('Country')->maxLength(255),
                    ]),

                Section::make('Map')
                    ->description('Used for the map on the Contact page and to help patients find you in search results.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('map_latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->placeholder('23.7461'),

                        TextInput::make('map_longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->placeholder('90.3742'),

                        Textarea::make('map_embed_url')
                            ->label('Google Maps embed link (optional)')
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('In Google Maps choose Share, then Embed a map, and paste only the "src" address here. Leave empty to build the map from the coordinates above.'),
                    ]),
            ]);
    }

    private static function hoursTab(): Tab
    {
        return Tab::make('Opening hours')
            ->icon('heroicon-o-clock')
            ->schema([
                Section::make()
                    ->description('The hours shown on your Contact page. These are what the chamber is OPEN — the times patients can actually book are set separately, under Practice → My hours.')
                    ->schema([
                        Repeater::make('working_hours')
                            ->hiddenLabel()
                            ->columns(4)
                            ->default(DoctorProfile::defaultWorkingHours())
                            // The week is fixed at seven days, so the doctor edits
                            // the rows rather than adding or removing them.
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->itemLabel(fn (array $state): string => DoctorProfile::DAYS[$state['day'] ?? ''] ?? 'Day')
                            ->schema([
                                Select::make('day')
                                    ->label('Day')
                                    ->options(DoctorProfile::DAYS)
                                    ->required()
                                    ->native(false),

                                Toggle::make('is_closed')
                                    ->label('Closed all day')
                                    ->live()
                                    ->inline(false),

                                TextInput::make('opens')
                                    ->label('Opens')
                                    ->type('time')
                                    ->visible(fn (Get $get): bool => ! $get('is_closed')),

                                TextInput::make('closes')
                                    ->label('Closes')
                                    ->type('time')
                                    ->visible(fn (Get $get): bool => ! $get('is_closed')),
                            ]),
                    ]),
            ]);
    }

    /** Pro-tier settings: what a consultation costs and how booking behaves. */
    private static function bookingTab(): Tab
    {
        return Tab::make('Fees & booking')
            ->icon('heroicon-o-banknotes')
            ->schema([
                Section::make('Consultation fee')
                    ->description('Shown on the booking screen and saved onto each appointment as it is made. Changing it later does not alter appointments already booked.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('consultation_fee')
                            ->label('Fee')
                            ->numeric()
                            ->minValue(0)
                            ->prefix(config('booking.payment.currency', 'BDT'))
                            ->helperText('Leave empty if you would rather discuss fees in person — no price will appear anywhere.'),
                    ]),

                Section::make('Booking window')
                    ->columns(2)
                    ->schema([
                        TextInput::make('booking_horizon_days')
                            ->label('How far ahead can patients book?')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->suffix('days')
                            ->placeholder((string) config('booking.horizon_days', 30))
                            ->helperText('Leave empty to use the site default of '.config('booking.horizon_days', 30).' days.'),
                    ]),

                Section::make('What to tell patients')
                    ->schema([
                        Textarea::make('booking_instructions')
                            ->label('Instructions on the confirmation')
                            ->rows(4)
                            ->helperText('Shown after booking and included in the confirmation email. For example: please arrive ten minutes early and bring any previous reports.'),
                    ]),
            ]);
    }

    private static function onlineTab(): Tab
    {
        return Tab::make('Social & search')
            ->icon('heroicon-o-globe-alt')
            ->schema([
                Section::make('Social profiles')
                    ->description('Leave a box empty to hide that icon from the footer.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('social_links.facebook')->label('Facebook')->url()->prefixIcon('heroicon-o-link'),
                        TextInput::make('social_links.instagram')->label('Instagram')->url()->prefixIcon('heroicon-o-link'),
                        TextInput::make('social_links.linkedin')->label('LinkedIn')->url()->prefixIcon('heroicon-o-link'),
                        TextInput::make('social_links.twitter')->label('X (Twitter)')->url()->prefixIcon('heroicon-o-link'),
                        TextInput::make('social_links.youtube')->label('YouTube')->url()->prefixIcon('heroicon-o-link'),
                    ]),

                Section::make('Search engine listing')
                    ->description('How your home page appears in Google. Leave blank to use your name and specialisation.')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Page title')
                            ->maxLength(255),

                        Textarea::make('meta_description')
                            ->label('Page description')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Around 150 characters reads best in search results.'),
                    ]),
            ]);
    }
}
