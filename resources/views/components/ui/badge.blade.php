{{--
    A small status pill.

        <x-ui.badge>Cardiology</x-ui.badge>
        <x-ui.badge tone="positive" dot>Confirmed</x-ui.badge>
        <x-ui.badge tone="brass">Featured</x-ui.badge>

    Square-ish and letterspaced, matching the buttons. The status tones are
    muted on purpose: a booking confirmation in signal green next to this
    palette looks like a system alert, not a clinic.
--}}

@props([
    // neutral | brass | dark | light | positive | caution | negative
    'tone' => 'neutral',
    'dot' => false,
])

@php
    $tones = [
        'neutral' => ['chip' => 'border-line bg-paper-shade text-muted', 'dot' => 'bg-muted'],
        'brass'   => ['chip' => 'border-brass/40 bg-brass-soft text-ink', 'dot' => 'bg-brass'],
        'dark'    => ['chip' => 'border-night-line bg-night text-white', 'dot' => 'bg-brass'],
        // For use on the dark sections, over photography or night panels.
        'light'   => ['chip' => 'border-white/20 bg-white/10 text-white', 'dot' => 'bg-brass'],
        'positive'=> ['chip' => 'border-positive/25 bg-positive-soft text-positive', 'dot' => 'bg-positive'],
        'caution' => ['chip' => 'border-caution/25 bg-caution-soft text-caution', 'dot' => 'bg-caution'],
        'negative'=> ['chip' => 'border-negative/25 bg-negative-soft text-negative', 'dot' => 'bg-negative'],
    ];

    $style = $tones[$tone] ?? $tones['neutral'];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-[3px] border px-2.5 py-1 text-[0.6875rem] font-semibold uppercase leading-none tracking-[0.1em]',
    $style['chip'],
]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $style['dot'] }}" aria-hidden="true"></span>
    @endif

    {{ $slot }}
</span>
