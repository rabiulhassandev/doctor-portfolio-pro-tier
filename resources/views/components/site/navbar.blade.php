{{--
    The site header.

    Two behaviours worth knowing about:

    * On the home page it starts transparent over the hero and turns solid once
      you scroll past 24px. Everywhere else it is solid from the start — an
      interior page has no hero for it to float over.

    * The mobile menu is a full-height drawer rather than a dropdown, because a
      dropdown containing eight links and two buttons ends up scrolling inside
      itself on a small phone.
--}}

@props(['transparent' => false])

@php
    $links = collect([
        ['label' => 'Home', 'route' => 'home'],
        ['label' => 'About', 'route' => 'about'],
        ['label' => 'Services', 'route' => 'services'],
        ['label' => 'Health videos', 'route' => 'videos.index', 'feature' => 'health_videos'],
        ['label' => 'Articles', 'route' => 'blog.index', 'feature' => 'blog'],
        ['label' => 'Gallery', 'route' => 'gallery', 'feature' => 'gallery'],
        ['label' => 'Questions', 'route' => 'faq', 'feature' => 'faq'],
        ['label' => 'Contact', 'route' => 'contact'],
    ])->filter(fn (array $link): bool => ! isset($link['feature']) || config('site.features.'.$link['feature']));

    $patient = auth('patient')->user();
    $bookingEnabled = config('site.features.booking');
@endphp

<header
    x-data="{ scrolled: {{ $transparent ? 'false' : 'true' }}, open: false }"
    @if ($transparent)
        @scroll.window="scrolled = window.scrollY > 24"
    @endif
    :class="scrolled
        ? 'border-line bg-paper/90 shadow-card backdrop-blur-md'
        : 'border-transparent bg-transparent'"
    class="fixed inset-x-0 top-0 z-50 border-b transition-all duration-300"
>
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8" aria-label="Main">

        {{-- Wordmark --}}
        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-3 rounded-lg">
            @if (config('site.logo'))
                <img src="{{ asset(config('site.logo')) }}" alt="{{ $doctor->name }}" class="h-9 w-auto">
            @else
                {{-- Initials in a hairline circle. Looks deliberate rather than
                     broken, so the demo needs no artwork. --}}
                <span class="flex size-10 items-center justify-center rounded-full border border-brand/20 bg-brand-soft text-sm font-semibold tracking-tight text-brand">
                    {{ Str::of($doctor->name)->replaceMatches('/^Dr\.?\s*/i', '')->explode(' ')->filter()->take(2)->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->implode('') }}
                </span>
            @endif

            <span class="flex min-w-0 flex-col leading-tight">
                <span class="truncate font-display text-lg text-ink">{{ $doctor->name }}</span>
                <span class="truncate text-[0.6875rem] font-semibold uppercase tracking-[0.14em] text-muted">
                    {{ $doctor->specialization }}
                </span>
            </span>
        </a>

        {{-- Desktop links --}}
        <ul class="hidden items-center gap-7 lg:flex">
            @foreach ($links as $link)
                <li>
                    <a href="{{ route($link['route']) }}"
                       @if (request()->routeIs($link['route'])) aria-current="page" @endif
                       @class([
                           'link-underline text-[0.9375rem] font-medium transition-colors',
                           'text-brand' => request()->routeIs($link['route']),
                           'text-ink hover:text-brand' => ! request()->routeIs($link['route']),
                       ])>
                        {{ $link['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>

        {{-- Desktop actions --}}
        <div class="hidden shrink-0 items-center gap-3 lg:flex">
            @if ($patient)
                <a href="{{ route('patient.dashboard') }}"
                   class="link-underline text-[0.9375rem] font-medium text-ink hover:text-brand">
                    My account
                </a>
            @else
                <a href="{{ route('patient.login') }}"
                   class="link-underline text-[0.9375rem] font-medium text-ink hover:text-brand">
                    Sign in
                </a>
            @endif

            @if ($bookingEnabled)
                <x-ui.button :href="route('booking')" size="sm">Book an appointment</x-ui.button>
            @elseif ($doctor->telHref())
                <x-ui.button :href="$doctor->telHref()" size="sm">Call the chamber</x-ui.button>
            @endif
        </div>

        {{-- Mobile menu trigger --}}
        <button type="button"
                @click="open = true"
                class="flex size-10 items-center justify-center rounded-full border border-line bg-surface text-ink lg:hidden"
                aria-label="Open menu">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>
    </nav>

    {{-- Mobile drawer --}}
    <div x-cloak x-show="open" class="lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
        {{-- Backdrop --}}
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             @click="open = false"
             class="fixed inset-0 z-40 bg-ink-deep/45 backdrop-blur-sm"></div>

        {{-- Panel --}}
        <div x-show="open"
             x-transition:enter="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-300"
             x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             {{-- Escape closes it, and focus is trapped inside while it is open. --}}
             @keydown.escape.window="open = false"
             x-trap.noscroll="open"
             class="fixed inset-y-0 right-0 z-50 flex w-[min(20rem,85vw)] flex-col overflow-y-auto border-l border-line bg-paper shadow-float">

            <div class="flex items-center justify-between border-b border-line px-5 py-4">
                <span class="font-display text-lg text-ink">Menu</span>
                <button type="button" @click="open = false"
                        class="flex size-9 items-center justify-center rounded-full border border-line bg-surface text-ink"
                        aria-label="Close menu">
                    <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <ul class="flex flex-col px-2 py-3">
                @foreach ($links as $link)
                    <li>
                        <a href="{{ route($link['route']) }}"
                           @if (request()->routeIs($link['route'])) aria-current="page" @endif
                           @class([
                               'block rounded-xl px-3 py-3 font-medium transition-colors',
                               'bg-brand-soft text-brand' => request()->routeIs($link['route']),
                               'text-ink hover:bg-paper-shade' => ! request()->routeIs($link['route']),
                           ])>
                            {{ $link['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-auto flex flex-col gap-3 border-t border-line px-5 py-5">
                @if ($patient)
                    <p class="text-sm text-muted">Signed in as <span class="font-semibold text-ink">{{ $patient->name }}</span></p>
                    <x-ui.button :href="route('patient.dashboard')" variant="secondary" block>My account</x-ui.button>
                @else
                    <x-ui.button :href="route('patient.login')" variant="secondary" block>Sign in</x-ui.button>
                @endif

                @if ($bookingEnabled)
                    <x-ui.button :href="route('booking')" block>Book an appointment</x-ui.button>
                @endif
            </div>
        </div>
    </div>
</header>

{{-- The header is fixed, so the page needs a matching gap underneath it —
     except on the home page, where the hero is meant to run up behind it. --}}
@unless ($transparent)
    <div class="h-[4.75rem]" aria-hidden="true"></div>
@endunless
