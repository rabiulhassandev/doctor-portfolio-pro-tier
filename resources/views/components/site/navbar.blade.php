{{--
    The site header.

    ---------------------------------------------------------------------------
    ALWAYS DARK, ALWAYS FIXED
    ---------------------------------------------------------------------------

    It starts transparent over whatever dark band opens the page, then becomes
    a solid night bar with a hairline once you scroll past it.

    That works on every page because every page now OPENS dark — the home hero,
    the interior page-hero band, the patient account header, the sign-in screen.
    Making that a rule rather than a per-page decision is what removed the
    `transparent` prop and the spacer div this component used to need: there is
    no longer a case where the header floats over white and disappears.

    If you add a page with a light top, give it a dark band or the wordmark
    will vanish.

    The mobile menu is a full-height drawer rather than a dropdown, because a
    dropdown holding eight links and two buttons ends up scrolling inside
    itself on a small phone.
--}}

@php
    $links = collect([
        ['label' => 'About', 'route' => 'about'],
        ['label' => 'Services', 'route' => 'services'],
        ['label' => 'Videos', 'route' => 'videos.index', 'feature' => 'health_videos'],
        ['label' => 'Journal', 'route' => 'blog.index', 'feature' => 'blog'],
        ['label' => 'Gallery', 'route' => 'gallery', 'feature' => 'gallery'],
        ['label' => 'Questions', 'route' => 'faq', 'feature' => 'faq'],
        ['label' => 'Contact', 'route' => 'contact'],
    ])->filter(fn (array $link): bool => ! isset($link['feature']) || config('site.features.'.$link['feature']));

    $patient = auth('patient')->user();
    $bookingEnabled = config('site.features.booking');

    $initials = Str::of($doctor->name)
        ->replaceMatches('/^Dr\.?\s*/i', '')
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))
        ->implode('');
@endphp

<header
    x-data="{ scrolled: false, open: false }"
    @scroll.window="scrolled = window.scrollY > 32"
    :class="scrolled
        ? 'border-night-line bg-night/95 backdrop-blur-md'
        : 'border-transparent bg-transparent'"
    class="fixed inset-x-0 top-0 z-50 border-b transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]"
>
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-5 py-4 sm:px-8" aria-label="Main">

        {{-- Wordmark --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3.5 rounded-sm">
            @if (config('site.logo'))
                <img src="{{ asset(config('site.logo')) }}" alt="{{ $doctor->name }}" class="h-9 w-auto">
            @else
                {{-- Initials in a brass-ruled square. Looks deliberate rather
                     than broken, so the demo needs no artwork. --}}
                <span class="flex size-10 items-center justify-center border border-brass/50 font-display text-lg leading-none text-brass">
                    {{ $initials }}
                </span>
            @endif

            <span class="hidden min-w-0 flex-col leading-tight sm:flex">
                <span class="truncate font-display text-xl text-white">{{ $doctor->name }}</span>
                <span class="truncate text-[0.625rem] font-semibold uppercase tracking-[0.2em] text-white/45">
                    {{ $doctor->specialization }}
                </span>
            </span>
        </a>

        {{-- Desktop links --}}
        <ul class="hidden items-center gap-8 lg:flex">
            @foreach ($links as $link)
                <li>
                    <a href="{{ route($link['route']) }}"
                       @if (request()->routeIs($link['route'])) aria-current="page" @endif
                       @class([
                           'link-underline text-[0.8125rem] font-medium uppercase tracking-[0.13em] transition-colors',
                           'text-brass' => request()->routeIs($link['route']),
                           'text-white/70 hover:text-white' => ! request()->routeIs($link['route']),
                       ])>
                        {{ $link['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- Desktop actions --}}
        <div class="hidden shrink-0 items-center gap-5 lg:flex">
            <a href="{{ $patient ? route('patient.dashboard') : route('patient.login') }}"
               class="link-underline text-[0.8125rem] font-medium uppercase tracking-[0.13em] text-white/70 transition-colors hover:text-white">
                {{ $patient ? 'My account' : 'Sign in' }}
            </a>

            @if ($bookingEnabled)
                <x-ui.button :href="route('booking')" size="sm">Book</x-ui.button>
            @elseif ($doctor->telHref())
                <x-ui.button :href="$doctor->telHref()" size="sm">Call</x-ui.button>
            @endif
        </div>

        {{-- Mobile menu trigger --}}
        <button type="button"
                @click="open = true"
                class="flex size-10 items-center justify-center border border-white/20 text-white transition-colors hover:border-brass hover:text-brass lg:hidden"
                aria-label="Open menu">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" d="M3.75 7h16.5M3.75 12h16.5m-16.5 5h16.5" />
            </svg>
        </button>
    </nav>

    {{-- Mobile drawer --}}
    <div x-cloak x-show="open" class="lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @click="open = false"
             class="fixed inset-0 z-40 bg-night/80 backdrop-blur-sm"></div>

        <div x-show="open"
             x-transition:enter="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-400"
             x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-250"
             x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             {{-- Escape closes it, and focus is trapped inside while open, so
                  tabbing cannot walk silently into the page behind. --}}
             @keydown.escape.window="open = false"
             x-trap.noscroll="open"
             class="fixed inset-y-0 right-0 z-50 flex w-[min(21rem,88vw)] flex-col overflow-y-auto border-l border-night-line bg-night shadow-float">

            <div class="flex items-center justify-between border-b border-night-line px-6 py-5">
                <span class="font-display text-xl text-white">Menu</span>
                <button type="button" @click="open = false"
                        class="flex size-9 items-center justify-center border border-white/20 text-white transition-colors hover:border-brass hover:text-brass"
                        aria-label="Close menu">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <ul class="flex flex-col px-3 py-4">
                <li>
                    <a href="{{ route('home') }}"
                       @class([
                           'block px-3 py-3.5 text-sm font-medium uppercase tracking-[0.13em] transition-colors',
                           'text-brass' => request()->routeIs('home'),
                           'text-white/75 hover:text-white' => ! request()->routeIs('home'),
                       ])>Home</a>
                </li>

                @foreach ($links as $link)
                    <li>
                        <a href="{{ route($link['route']) }}"
                           @if (request()->routeIs($link['route'])) aria-current="page" @endif
                           @class([
                               'block px-3 py-3.5 text-sm font-medium uppercase tracking-[0.13em] transition-colors',
                               'text-brass' => request()->routeIs($link['route']),
                               'text-white/75 hover:text-white' => ! request()->routeIs($link['route']),
                           ])>
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-auto flex flex-col gap-3 border-t border-night-line px-6 py-6">
                @if ($patient)
                    <p class="text-sm text-white/55">
                        Signed in as <span class="font-semibold text-white">{{ $patient->name }}</span>
                    </p>
                    <x-ui.button :href="route('patient.dashboard')" variant="outline-light" block>My account</x-ui.button>
                @else
                    <x-ui.button :href="route('patient.login')" variant="outline-light" block>Sign in</x-ui.button>
                @endif

                @if ($bookingEnabled)
                    <x-ui.button :href="route('booking')" block>Book a consultation</x-ui.button>
                @endif
            </div>
        </div>
    </div>
</header>
