{{--
    The one button on the site.

    Every call to action goes through here, which is the difference between a
    site whose buttons agree with each other and one where each page invented
    its own.

        <x-ui.button :href="route('booking')">Book a consultation</x-ui.button>
        <x-ui.button variant="brass" size="lg">Book</x-ui.button>
        <x-ui.button variant="outline-light" :href="$tel">Call the chamber</x-ui.button>

    ---------------------------------------------------------------------------
    WHY THESE ARE SQUARE
    ---------------------------------------------------------------------------

    Fully-rounded pills read as friendly and app-like. This palette is aiming
    at the opposite: considered, quiet, slightly formal. A 2px radius and wide
    letterspacing on a small uppercase label does more for that than any amount
    of colour would.

    Renders an <a> when given href, a <button> otherwise — so a link never
    pretends to be a button, which matters to screen readers and to anyone
    middle-clicking.
--}}

@props([
    'href' => null,
    // brass | dark | outline | outline-light | ghost | danger
    'variant' => 'brass',
    // sm | md | lg
    'size' => 'md',
    'type' => 'button',
    'iconRight' => null,
    'block' => false,
])

@php
    $base = 'group relative inline-flex items-center justify-center gap-2.5 rounded-[3px] '
        . 'font-semibold uppercase tracking-[0.12em] '
        . 'transition-all duration-400 ease-[cubic-bezier(0.16,1,0.3,1)] '
        . 'focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-brass '
        . 'disabled:cursor-not-allowed disabled:opacity-50';

    $sizes = [
        'sm' => 'px-4 py-2.5 text-[0.6875rem]',
        'md' => 'px-6 py-3.5 text-xs',
        'lg' => 'px-8 py-4 text-[0.8125rem]',
    ];

    /*
     | The lift is 1px, not 4. A button that jumps under the cursor draws
     | attention to the animation; one that settles draws attention to itself.
     */
    $variants = [
        // The primary action. Brass on near-black text — the only place the
        // accent is used as a fill anywhere on the site.
        'brass' => 'bg-brass text-night shadow-card hover:-translate-y-px hover:bg-brass-bright hover:shadow-lift',

        // For use on the light sections, where brass-on-paper would be weak.
        'dark' => 'bg-night text-white shadow-card hover:-translate-y-px hover:bg-night-soft hover:shadow-lift',

        // Secondary, on light backgrounds.
        'outline' => 'border border-line-strong text-ink hover:-translate-y-px hover:border-night hover:bg-night hover:text-white',

        // Secondary, on the dark hero and footer.
        'outline-light' => 'border border-white/25 text-white hover:-translate-y-px hover:border-brass hover:text-brass',

        'ghost' => 'text-ink hover:text-brass',

        'danger' => 'bg-negative text-white shadow-card hover:-translate-y-px hover:shadow-lift',
    ];

    /*
     | An unknown variant name falls back to `brass` — which is silent, and
     | which is how seven `variant="secondary"` buttons (a name this component
     | never had) ended up rendered as solid brass across the patient account
     | and the booking wizard. Every one of them was meant to be the quiet
     | option, and the palette's one rule is that the accent is spent once per
     | view.
     |
     | So it still falls back rather than breaking a live site, but it shouts
     | while you are building. Debug mode only: a typo a buyer's developer
     | introduces after launch should not take their site down.
     */
    if (config('app.debug') && ! isset($variants[$variant])) {
        throw new \InvalidArgumentException(
            "Unknown button variant [{$variant}]. Use one of: ".implode(', ', array_keys($variants)).'.'
        );
    }

    $classes = implode(' ', [
        $base,
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['brass'],
        $block ? 'w-full' : '',
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}

        @if ($iconRight)
            {{-- Nudges forward on hover: the smallest possible hint that this
                 leads somewhere, without animating the whole button. --}}
            <span class="transition-transform duration-400 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:translate-x-1" aria-hidden="true">
                {!! $iconRight !!}
            </span>
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        {{ $slot }}

        @if ($iconRight)
            <span class="transition-transform duration-400 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:translate-x-1" aria-hidden="true">
                {!! $iconRight !!}
            </span>
        @endif
    </button>
@endif
