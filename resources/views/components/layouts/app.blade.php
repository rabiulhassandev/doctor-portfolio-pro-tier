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
    use App\Models\SeoSetting;
    use App\Support\Seo;

    $siteName = $doctor->name ?: config('site.name');

    /*
     | Every fallback chain in the <head> lives in App\Support\Seo, not here.
     |
     | There are five sources for each of these — the page, the admin's
     | per-page override, the site-wide default, the doctor's profile and
     | config/site.php — and expressing that in Blade meant nobody could test
     | it and nobody could see the order at a glance. See the class for the
     | rule, written down once.
     */
    $seo = Seo::resolve($title, $description, $image);
    $seoSettings = SeoSetting::current();

    $metaTitle = $seo->title;
    $metaDescription = $seo->description;
    $metaImage = $seo->image;

    $colors = config('site.colors');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $seo->canonical }}">

    {{-- Crawler instructions. `max-image-preview:large` is the cheap win: it is
         what allows a full-size thumbnail beside the result rather than a
         postage stamp, and it costs one word. Becomes `noindex, nofollow` while
         the staging switch on the SEO settings screen is on. --}}
    <meta name="robots" content="{{ $seo->robots }}">

    {{-- Ownership verification for Search Console and friends, pasted in by the
         doctor on the SEO settings screen. --}}
    @foreach (Seo::verificationTags() as $name => $content)
        <meta name="{{ $name }}" content="{{ $content }}">
    @endforeach

    {{-- Open Graph / Twitter, so shared links look right in messages and feeds. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $seo->canonical }}">
    @if ($metaImage)
        <meta property="og:image" content="{{ $metaImage }}">
        <meta property="og:image:alt" content="{{ $metaTitle }}">
    @endif
    <meta name="twitter:card" content="{{ $metaImage ? 'summary_large_image' : 'summary' }}">
    @if ($seo->twitterHandle)
        <meta name="twitter:site" content="{{ $seo->twitterHandle }}">
    @endif

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

    {{--
        schema.org for the site itself: who publishes it, in what language, and
        the organisation behind it. Distinct from the Physician block that the
        home, about and contact pages push — that one describes the practice,
        this one describes the website.

        It is also the part an AI assistant actually reasons over. A question
        like "a woman cardiologist in Dhanmondi who consults in Bangla" is
        answered from areaServed and availableLanguage, not from prose.
    --}}
    @foreach (Seo::siteSchema() as $schemaBlock)
        <script type="application/ld+json">
            {!! json_encode($schemaBlock, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endforeach

    {{-- Pages push their own schema.org markup here. --}}
    @stack('schema')
    @stack('head')

    {{--
        Analytics.

        Never loaded on the booking pages, inside a patient account, or on a
        document download — see Seo::analyticsAllowedHere(). On a medical site
        the URL alone says what somebody is worried about, and that is not the
        doctor's to hand to a third party.
    --}}
    @if ($seo->analyticsAllowed)
        @if ($seoSettings->gtm_container_id)
            <script>
                (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
                var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
                j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','{{ $seoSettings->gtm_container_id }}');
            </script>
        @endif

        @if ($seoSettings->ga4_measurement_id)
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $seoSettings->ga4_measurement_id }}"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '{{ $seoSettings->ga4_measurement_id }}', { anonymize_ip: true });
            </script>
        @endif

        @if ($seoSettings->meta_pixel_id)
            <script>
                !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
                n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
                t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
                'https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', '{{ $seoSettings->meta_pixel_id }}');
                fbq('track', 'PageView');
            </script>
        @endif
    @endif

    {{-- The doctor's own <head> code, from the SEO settings screen. Rendered
         exactly as typed — the form says so in as many words. --}}
    @if ($seo->analyticsAllowed && filled($seoSettings->head_scripts))
        {!! $seoSettings->head_scripts !!}
    @endif
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

    {{-- Tag Manager's <noscript> half. Useless on its own, so it only appears
         where the script above it did. --}}
    @if ($seo->analyticsAllowed && $seoSettings->gtm_container_id)
        <noscript>
            <iframe src="https://www.googletagmanager.com/ns.html?id={{ $seoSettings->gtm_container_id }}"
                    height="0" width="0" style="display:none;visibility:hidden"></iframe>
        </noscript>
    @endif

    @if ($seo->analyticsAllowed && filled($seoSettings->body_scripts))
        {!! $seoSettings->body_scripts !!}
    @endif
</body>
</html>
