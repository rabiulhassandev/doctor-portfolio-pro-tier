<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\BookingsPerWeek;
use App\Filament\Widgets\PracticeOverview;
use App\Filament\Widgets\TodaysAppointments;
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
            // The same typeface as the public site, self-hosted via Bunny
            // rather than Google so the panel makes no request to an ad network.
            ->font('Instrument Sans', provider: BunnyFontProvider::class)
            ->colors([
                'primary' => Color::hex(config('site.colors.primary')),
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
            // Forms and tables breathe better than at Filament's default
            // full-bleed width on a large clinic monitor.
            ->maxContentWidth(Width::ScreenTwoExtraLarge)
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
             | The rest of the brand palette, handed to the theme stylesheet as
             | CSS custom properties.
             |
             | Filament only generates variables for the colours passed to
             | ->colors() above, and the theme needs the accent as well. Doing it
             | here keeps theme.css free of hex codes, so a rebrand remains a
             | change to config/site.php and nothing else.
             */
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render(
                    '<style>:root{--brand-accent:{{ $accent }};--brand-ink:{{ $ink }};}</style>',
                    [
                        'accent' => config('site.colors.accent'),
                        'ink' => config('site.colors.ink'),
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
