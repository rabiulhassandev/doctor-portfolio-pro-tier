{{--
    The shell every public page sits inside.

    Usage from a page view:

        <x-layouts.app title="About" description="A short summary for Google.">
            ... page content ...
        </x-layouts.app>

    $doctor is available in here (and in every other view) because
    AppServiceProvider shares it globally.
--}}

@props([
    // Page-specific SEO. Both fall back to the doctor's saved defaults.
    'title' => null,
    'description' => null,
    // Social-sharing image; falls back to the doctor's photo.
    'image' => null,
    /*
     | Accepted but no longer used. The navbar is always dark and always
     | transparent-until-scrolled, because every page now opens with a dark
     | band — see the note at the top of components/site/navbar.blade.php.
     |
     | Kept as a prop so that pages passing it do not error, and so that anyone
     | grepping for it finds this explanation rather than silence.
     */
    'transparentNav' => true,
    // Hide the floating action bar on pages that have their own sticky footer
    // (the booking wizard, mainly).
    'hideActionBar' => false,
])

@php
    use App\Support\Media;

    $siteName = $doctor->name ?: config('site.name');

    // "About | Dr. Tahmina Rahman" — never "Dr. Tahmina Rahman | Dr. Tahmina Rahman".
    $metaTitle = $title
        ? $title . ' | ' . $siteName
        : ($doctor->meta_title ?: $siteName . ' — ' . $doctor->specialization);

    $metaDescription = $description
        ?: ($doctor->meta_description ?: ($doctor->short_bio ?: config('site.meta_description')));

    // Google truncates around 160 characters. `Str::limit` appends its ellipsis
    // *after* the limit, so ask for 157 to land on 160 in the worst case, and
    // work it out once rather than in each of the three tags that print it.
    $metaDescription = Str::limit(strip_tags($metaDescription), 157);

    // Social crawlers fetch this on their own servers, so it has to be absolute.
    $metaImage = $image
        ? Media::absoluteUrl($image)
        : Media::absoluteUrl($doctor->photo);

    $colors = config('site.colors');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph / Twitter, so shared links look right in messages and feeds. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($metaImage)
        <meta property="og:image" content="{{ $metaImage }}">
    @endif
    <meta name="twitter:card" content="{{ $metaImage ? 'summary_large_image' : 'summary' }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">

    {{--
        Brand colours from config/site.php, written in as CSS custom properties.
        This is what makes a rebrand a one-file change: resources/css/app.css maps
        Tailwind's `brand`/`accent` colours onto these variables.
    --}}
    <style>
        :root {
            --brand-night: {{ $colors['night'] }};
            --brand-night-soft: {{ $colors['night_soft'] }};
            --brand-night-line: {{ $colors['night_line'] }};

            --brand-brass: {{ $colors['brass'] }};
            --brand-brass-bright: {{ $colors['brass_bright'] }};
            --brand-brass-soft: {{ $colors['brass_soft'] }};

            --brand-paper: {{ $colors['paper'] }};
            --brand-paper-shade: {{ $colors['paper_shade'] }};
            --brand-surface: {{ $colors['surface'] }};

            --brand-ink: {{ $colors['ink'] }};
            --brand-muted: {{ $colors['muted'] }};

            --brand-line: {{ $colors['line'] }};
            --brand-line-strong: {{ $colors['line_strong'] }};

            --brand-positive: {{ $colors['positive'] }};
            --brand-positive-light: {{ $colors['positive_light'] }};
            --brand-caution: {{ $colors['caution'] }};
            --brand-caution-light: {{ $colors['caution_light'] }};
            --brand-negative: {{ $colors['negative'] }};
            --brand-negative-light: {{ $colors['negative_light'] }};
        }
    </style>
    {{-- The browser chrome on a phone picks this up. The navbar is dark, so
         this is the night colour rather than the page background — otherwise
         the status bar and the header disagree at the top of the screen. --}}
    <meta name="theme-color" content="{{ $colors['night'] }}">

    {{-- Alpine only hides x-cloak elements once it has booted; without this rule
         they flash on screen while the page is still loading. --}}
    <style>[x-cloak] { display: none !important; }</style>

    {{--
        The webfonts.

        This line is not optional and its absence is silent, which is a nasty
        combination: the families named in vite.config.js are downloaded and
        bundled at build time, but nothing puts them on the page unless
        Vite::fonts() is called. Without it every stack quietly falls through to
        the system fallback — Georgia for the headings, Segoe UI for the body —
        and the site looks *almost* right, which is the hardest kind of wrong to
        notice.

        SolaimanLipi is not part of this: it is declared directly in
        resources/css/app.css because it does not come from Bunny.
    --}}
    {{ Vite::fonts() }}

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{--
        Livewire's runtime configuration.

        Paired with the manual bundling in resources/js/app.js, which imports
        Alpine FROM Livewire rather than separately. Both halves are required:
        drop this directive and every Livewire component on the page silently
        stops responding. See the long note at the top of app.js.
    --}}
    @livewireScriptConfig

    {{-- Pages push their schema.org markup here. --}}
    @stack('schema')
    @stack('head')
</head>

{{-- The bottom padding clears the fixed call/book bar, which only exists below
     `lg` — above that the padding is dropped so the footer sits on the fold. --}}
<body class="bg-paper font-sans text-ink antialiased {{ $hideActionBar ? '' : 'pb-20 lg:pb-0' }}">
    {{-- Keyboard and screen-reader users can jump straight past the navigation. --}}
    <a href="#main"
       class="sr-only focus:not-sr-only focus:fixed focus:left-6 focus:top-6 focus:z-[100] focus:rounded-full focus:bg-night focus:px-5 focus:py-2.5 focus:text-sm focus:text-white">
        Skip to main content
    </a>

    <x-site.navbar />

    <main id="main">
        {{ $slot }}
    </main>

    <x-site.footer />

    @if (config('site.features.whatsapp_button'))
        <x-site.whatsapp-button />
    @endif

    @unless ($hideActionBar)
        <x-site.mobile-action-bar />
    @endunless

    @stack('scripts')
</body>
</html>
