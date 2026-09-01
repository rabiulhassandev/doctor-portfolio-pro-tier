<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\BookingsPerWeek;
use App\Filament\Widgets\PracticeOverview;
use App\Filament\Widgets\TodaysAppointments;
use App\Models\DoctorProfile;
use App\Support\Media;
use Filament\Auth\Pages\Login;
use Filament\FontProviders\BunnyFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The staff admin panel at /admin.
 *
 * Authenticates on the `web` guard, which is the STAFF guard. Patients use a
 * separate `patient` guard and never touch this panel — see config/auth.php.
 *
 * The visual language is set in two places and only two: the colours below,
 * which are read from config/site.php, and resources/css/filament/admin/theme.css,
 * which mixes everything else from Filament's generated colour ramp. Neither
 * hardcodes a hex, so rebranding stays a one-file change.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->brandName(config('site.name'))
            /*
             | Inter — a neutral interface face, and deliberately NOT either of
             | the public site's fonts. A dense table of appointment times wants
             | an even, unremarkable grotesque; the display serif that makes the
             | public hero look considered would be actively unhelpful here.
             |
             | Self-hosted via Bunny rather than Google, so the panel makes no
             | request to an ad network.
             */
            ->font('Inter', provider: BunnyFontProvider::class)
            ->colors([
                /*
                 | The panel's blue comes from config/site.php's `admin` block,
                 | which is a DIFFERENT palette from the public website's. The
                 | public site is dark navy and brass; this is a bright working
                 | tool. Trying to serve both from one palette makes a worse
                 | job of each.
                 */
                'primary' => Color::hex(config('site.admin.primary')),
                /*
                 | Filament tints its greys towards the primary hue when you let
                 | it. Slate keeps long appointment tables readable instead of
                 | turning the whole panel blue.
                 */
                'gray' => Color::Slate,
            ])
            ->favicon(asset('favicon.ico'))
            ->darkMode(true)
            ->sidebarCollapsibleOnDesktop()
            /*
             | Full width, unlike the public site.
             |
             | This is a working panel: the appointment table has eight columns
             | and a receptionist wants all of them at once. Constraining it to
             | a comfortable reading measure would be applying a rule from the
             | wrong kind of page.
             */
            ->maxContentWidth(Width::Full)
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            /*
             | Sections in the order the practice needs them: today's work
             | first, the people it concerns second, the marketing site last.
             */
            ->navigationGroups([
                NavigationGroup::make('Practice')
                    ->icon('heroicon-o-calendar-days'),
                NavigationGroup::make('Patients')
                    ->icon('heroicon-o-user-group'),
                NavigationGroup::make('Website content')
                    ->icon('heroicon-o-document-text'),
                /*
                 | Last, and separate from "Website content" on purpose. Writing
                 | an article and tuning how the site is found are different
                 | jobs done on different days, and a doctor looking for one
                 | should not have to read past the other.
                 */
                NavigationGroup::make('Search & visibility')
                    ->icon('heroicon-o-magnifying-glass'),
            ])
            // Staff constantly want to check how an edit actually looks.
            ->navigationItems([
                NavigationItem::make('View the website')
                    ->url('/', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->sort(99),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                PracticeOverview::class,
                BookingsPerWeek::class,
                TodaysAppointments::class,
            ])
            /*
             | The rest of the admin palette, handed to the theme stylesheet as
             | CSS custom properties.
             |
             | Filament only generates variables for the colours passed to
             | ->colors() above, and the theme needs the canvas, the sidebar and
             | the tint as well. Doing it here keeps theme.css free of hex codes
             | entirely, so a rebrand remains a change to config/site.php.
             */
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render(
                    '<style>:root{'
                    .'--brand-canvas:{{ $canvas }};'
                    .'--brand-sidebar:{{ $sidebar }};'
                    .'--brand-sidebar-ink:{{ $sidebarInk }};'
                    .'--brand-tint:{{ $tint }};'
                    .'}</style>',
                    [
                        'canvas' => config('site.admin.canvas'),
                        'sidebar' => config('site.admin.sidebar'),
                        'sidebarInk' => config('site.admin.sidebar_ink'),
                        'tint' => config('site.admin.brand_tint'),
                    ],
                ),
            )
            /*
            |------------------------------------------------------------------
            | The sign-in screen
            |------------------------------------------------------------------
            |
            | The ONE place the panel wears the public website's clothes: deep
            | navy, brass, and a photograph of the chamber. Everything past the
            | login form goes straight back to being a bright working tool.
            |
            | That is not an inconsistency, it is the point. The front door is
            | seen before anyone is working and it is what a buyer shows people;
            | a flat blue rectangle there undersells the product. The dense
            | tables behind it want contrast and legibility, not atmosphere.
            |
            | BOTH HOOKS ARE SCOPED TO THE LOGIN PAGE, and that scoping is
            | load-bearing rather than tidiness: the public palette must not
            | leak into the rest of the panel, and a test asserts the public
            | brass appears nowhere in the dashboard's HTML.
            */
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render(
                    '<style>.fi-simple-layout{'
                    .'--login-night:{{ $night }};'
                    .'--login-night-soft:{{ $nightSoft }};'
                    .'--login-brass:{{ $brass }};'
                    .'--login-brass-bright:{{ $brassBright }};'
                    .'@if ($photo)--login-photo:url("{{ $photo }}");@endif'
                    .'}</style>',
                    [
                        'night' => config('site.colors.night'),
                        'nightSoft' => config('site.colors.night_soft'),
                        'brass' => config('site.colors.brass'),
                        'brassBright' => config('site.colors.brass_bright'),
                        // Null on a fresh install before the seeders have run.
                        // The CSS falls back to a plain navy field.
                        'photo' => Media::banner(key: 'admin.login'),
                    ],
                ),
                scopes: Login::class,
            )
            /*
             | The identity panel beside the form.
             |
             | Injected rather than built by overriding Filament's Login page,
             | because a subclass would have to carry a copy of the auth form's
             | Blade and would need revisiting on every Filament upgrade. A
             | render hook and a stylesheet survive that; this is markup that
             | sits next to the form, not markup that replaces it.
             */
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): string => Blade::render(
                    <<<'BLADE'
                    <aside class="login-brand" aria-hidden="true">
                        <div class="login-brand-inner">
                            <p class="login-brand-eyebrow"><span></span>{{ $eyebrow }}</p>
                            <p class="login-brand-name">{{ $name }}</p>
                            @if ($specialization)
                                <p class="login-brand-role">{{ $specialization }}</p>
                            @endif
                            <p class="login-brand-lead">{{ $lead }}</p>
                        </div>
                    </aside>
                    BLADE,
                    [
                        'eyebrow' => 'Practice administration',
                        'name' => config('site.name'),
                        'specialization' => DoctorProfile::current()->specialization,
                        'lead' => 'Appointments, patients, payments and everything on the website — in one place.',
                    ],
                ),
                scopes: Login::class,
            )
            /*
             | A way back to the public site.
             |
             | Without it the sign-in screen is a dead end for anybody who
             | arrived at /admin by mistake — and on a template, somebody always
             | does.
             */
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => Blade::render(
                    '<a href="{{ url(\'/\') }}" class="login-back">&larr; Back to the website</a>'
                ),
                scopes: Login::class,
            )
            /*
             | The centred credit line under the content, as in the reference
             | design. Reads config/site.php so a reseller can set or remove it
             | without touching a Blade file.
             */
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => Blade::render(
                    '<div class="admin-footer">&copy; {{ $year }} {{ $name }}'
                    .'@if ($credit) <span aria-hidden="true">&mdash;</span> {!! $credit !!}@endif'
                    .'</div>',
                    [
                        'year' => now()->year,
                        'name' => config('site.name'),
                        'credit' => config('site.credit'),
                    ],
                ),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                // Laravel 13's rename of VerifyCsrfToken.
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
