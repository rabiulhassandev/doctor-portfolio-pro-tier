{{--
    The one button on the site.

    Every call to action goes through here, which is the difference between a
    site whose buttons agree with each other and one where each page invented
    its own. (The Standard tier repeats the class string inline on every page;
    this is the fix.)

        <x-ui.button :href="route('booking')">Book an appointment</x-ui.button>
        <x-ui.button variant="secondary" type="submit">Save</x-ui.button>
        <x-ui.button variant="ghost" size="sm" icon="arrow-right">Read more</x-ui.button>

    Renders an <a> when given href, a <button> otherwise — so a link never
    pretends to be a button or vice versa, which matters to screen readers and
    to anyone middle-clicking.
--}}

@props([
    'href' => null,
    // primary | secondary | ghost | danger | white
    'variant' => 'primary',
    // sm | md | lg
    'size' => 'md',
    'type' => 'button',
    'iconRight' => null,
    'block' => false,
])

@php
    $base = 'group relative inline-flex items-center justify-center gap-2 rounded-full font-semibold '
        . 'transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] '
        . 'focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-accent '
        . 'disabled:cursor-not-allowed disabled:opacity-55';

    $sizes = [
        'sm' => 'px-4 py-2 text-sm',
        'md' => 'px-6 py-3 text-[0.9375rem]',
        'lg' => 'px-8 py-4 text-base',
    ];

    /*
     | The lift is small — 1px, not 4. A button that jumps under the cursor
     | draws attention to the animation; one that settles draws attention to
     | itself. The shadow does most of the work.
     */
    $variants = [
        'primary' => 'bg-brand text-white shadow-card hover:-translate-y-px hover:bg-brand-dark hover:shadow-lift',

        'secondary' => 'border border-line-strong bg-surface text-ink shadow-card '
            . 'hover:-translate-y-px hover:border-brand hover:text-brand hover:shadow-lift',

        'ghost' => 'text-brand hover:bg-brand-soft',

        'danger' => 'bg-negative text-white shadow-card hover:-translate-y-px hover:shadow-lift',

        // For use on the dark footer and on photographic heroes.
        'white' => 'bg-white text-ink shadow-lift hover:-translate-y-px hover:shadow-float',
    ];

    $classes = implode(' ', [
        $base,
        $sizes[$size] ?? $sizes['md'],
        $variants[$variant] ?? $variants['primary'],
        $block ? 'w-full' : '',
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}

        @if ($iconRight)
            {{-- Nudges forward on hover: the smallest possible hint that this
                 leads somewhere, without animating the whole button. --}}
            <span class="transition-transform duration-300 group-hover:translate-x-0.5" aria-hidden="true">
                {!! $iconRight !!}
            </span>
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        {{ $slot }}

        @if ($iconRight)
            <span class="transition-transform duration-300 group-hover:translate-x-0.5" aria-hidden="true">
                {!! $iconRight !!}
            </span>
        @endif
    </button>
@endif
