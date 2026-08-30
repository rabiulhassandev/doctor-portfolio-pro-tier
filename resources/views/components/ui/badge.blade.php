{{--
    A small status pill.

        <x-ui.badge>Cardiology</x-ui.badge>
        <x-ui.badge tone="positive" dot>Confirmed</x-ui.badge>

    The tones map onto the status colours in config/site.php, which are
    deliberately muted: a booking confirmation in signal green next to this
    palette reads as a system alert rather than as a clinic.
--}}

@props([
    // neutral | brand | accent | positive | caution | negative
    'tone' => 'neutral',
    // Show a small filled circle before the label.
    'dot' => false,
])

@php
    $tones = [
        'neutral' => ['chip' => 'border-line bg-paper-shade text-muted', 'dot' => 'bg-muted'],
        'brand' => ['chip' => 'border-brand/15 bg-brand-soft text-brand', 'dot' => 'bg-brand'],
        'accent' => ['chip' => 'border-accent/20 bg-accent-soft text-accent', 'dot' => 'bg-accent'],
        'positive' => ['chip' => 'border-positive/20 bg-positive-soft text-positive', 'dot' => 'bg-positive'],
        'caution' => ['chip' => 'border-caution/20 bg-caution-soft text-caution', 'dot' => 'bg-caution'],
        'negative' => ['chip' => 'border-negative/20 bg-negative-soft text-negative', 'dot' => 'bg-negative'],
    ];

    $style = $tones[$tone] ?? $tones['neutral'];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold leading-none',
    $style['chip'],
]) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full {{ $style['dot'] }}" aria-hidden="true"></span>
    @endif

    {{ $slot }}
</span>
