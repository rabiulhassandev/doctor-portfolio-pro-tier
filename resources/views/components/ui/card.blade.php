{{--
    The surface everything sits on.

        <x-ui.card>…</x-ui.card>
        <x-ui.card :href="route('blog.show', $post)" padding="none">…</x-ui.card>

    Three deliberate choices, and they are what stop this reading as a template:

      * A HAIRLINE border, not an outline. `line` is a barely-there warm grey;
        a 1px mid-blue rectangle around every card is the fastest way to make a
        site look like a wireframe.
      * A two-layer shadow tinted with the ink rather than pure black, so it
        sits on warm paper without going grey.
      * Generous rounding (1rem), used consistently. Mixed radii across a page
        are one of the things people notice without being able to say why.
--}}

@props([
    'href' => null,
    // default | none | tight | loose
    'padding' => 'default',
    // Whether it lifts on hover. Only meaningful when the whole card is a link.
    'interactive' => null,
])

@php
    $isLink = filled($href);
    $lifts = $interactive ?? $isLink;

    $paddings = [
        'none' => '',
        'tight' => 'p-5',
        'default' => 'p-6 sm:p-7',
        'loose' => 'p-8 sm:p-10',
    ];

    $classes = implode(' ', [
        'relative overflow-hidden rounded-2xl border border-line bg-surface shadow-card',
        $paddings[$padding] ?? $paddings['default'],
        $lifts ? 'card-lift hover:border-line-strong hover:shadow-lift' : '',
        $isLink ? 'block focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-accent' : '',
    ]);
@endphp

@if ($isLink)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->class($classes) }}>
        {{ $slot }}
    </div>
@endif
