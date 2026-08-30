{{--
    The banner at the top of every page except the home page.

        <x-ui.page-hero
            eyebrow="Patient education"
            title="Health videos"
            lead="Short films about the conditions I treat." />

    Dark, and full-bleed. Because the navbar is transparent over it, this is
    also what stops interior pages having a floating header over white — the
    band gives the navigation something to sit on.

    It is a BAND, not a second hero: no photography, no call to action, no
    great height. Interior pages exist to be read, and a full-screen image at
    the top of every one of them pushes the actual content below the fold.
--}}

@props([
    'eyebrow' => null,
    'title' => '',
    'lead' => null,
])

<header class="surface-grain relative isolate overflow-hidden bg-night">
    {{-- A single soft bloom, well off to one side and low in the band, so the
         heading sits on the darkest part rather than fighting the light. --}}
    <div class="pointer-events-none absolute inset-0 -z-10"
         style="background: radial-gradient(38rem 22rem at 82% 120%, color-mix(in oklab, var(--brand-brass) 16%, transparent), transparent 70%);"
         aria-hidden="true"></div>

    {{-- The brass hairline along the bottom edge: the one detail that makes
         the band read as deliberate rather than as a dark rectangle. --}}
    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-brass/50 to-transparent"
         aria-hidden="true"></div>

    <div class="mx-auto max-w-7xl px-5 pb-16 pt-32 sm:px-8 sm:pb-20 sm:pt-40">
        <div class="flex max-w-3xl flex-col gap-5" data-reveal>
            @if ($eyebrow)
                <div class="flex items-center gap-3">
                    <span class="rule-brass"></span>
                    <p class="eyebrow eyebrow-light">{{ $eyebrow }}</p>
                </div>
            @endif

            <h1 class="text-[2.75rem] leading-[1.03] text-white sm:text-6xl lg:text-7xl">{{ $title }}</h1>

            @if ($lead)
                <p class="max-w-[38rem] text-lg leading-relaxed text-white/65">{{ $lead }}</p>
            @endif

            {{ $slot }}
        </div>
    </div>
</header>
