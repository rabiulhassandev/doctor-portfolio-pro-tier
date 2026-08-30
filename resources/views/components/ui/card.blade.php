{{--
    The surface everything sits on.

        <x-ui.card>…</x-ui.card>
        <x-ui.card tone="dark">…</x-ui.card>
        <x-ui.card :href="route('blog.show', $post)" padding="none">…</x-ui.card>

    ---------------------------------------------------------------------------
    THE BRASS RULE
    ---------------------------------------------------------------------------

    Cards have almost no decoration: a hairline border, a very small radius, a
    shadow you have to look for. The one gesture is a brass rule that draws
    itself across the top edge on hover, left to right.

    That is the whole hover state. No colour shift, no scale, no border glow —
    a grid where every card lights up differently on hover is a grid nobody can
    scan. One line, always in the same place.
--}}

@props([
    'href' => null,
    // light | dark | glass
    'tone' => 'light',
    // default | none | tight | loose
    'padding' => 'default',
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

    $tones = [
        'light' => 'border-line bg-surface text-ink',
        // For cards sitting on the night sections.
        'dark' => 'border-night-line bg-night-soft text-white',
        // Only over photography or the dark hero — see the note in app.css.
        'glass' => 'glass text-white',
    ];

    $classes = implode(' ', [
        'group/card relative overflow-hidden rounded-[4px] border shadow-card',
        $tones[$tone] ?? $tones['light'],
        $paddings[$padding] ?? $paddings['default'],
        $lifts ? 'card-lift hover:shadow-lift' : '',
        $isLink ? 'block focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-brass' : '',
    ]);
@endphp

@php
    // The brass rule. Rendered for anything that lifts, so a static card
    // carries no promise of interactivity it cannot keep.
    $rule = $lifts
        ? '<span aria-hidden="true" class="absolute inset-x-0 top-0 h-px origin-left scale-x-0 bg-brass '
            . 'transition-transform duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover/card:scale-x-100"></span>'
        : '';
@endphp

@if ($isLink)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        {!! $rule !!}
        {{ $slot }}
    </a>
@else
    <div {{ $attributes->class($classes) }}>
        {!! $rule !!}
        {{ $slot }}
    </div>
@endif
