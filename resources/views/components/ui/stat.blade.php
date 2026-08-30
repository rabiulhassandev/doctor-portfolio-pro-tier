{{--
    One figure in the credentials band: "20 years of practice".

        <x-ui.stat value="20+" label="Years in practice" />

    The number is set in the display serif at a size that carries across a
    section; the label stays small, uppercase and quiet so the figure does the
    work. Left-aligned, with a brass rule above — the centred version of this
    is what every template does.
--}}

@props([
    'value',
    'label',
    // Set true on the dark sections.
    'onDark' => false,
])

<div {{ $attributes->class('flex flex-col gap-2.5') }}>
    <span class="rule-brass"></span>

    <span @class([
        'font-display text-5xl leading-none tabular-nums sm:text-6xl',
        'text-white' => $onDark,
        'text-ink' => ! $onDark,
    ])>{{ $value }}</span>

    <span @class([
        'text-[0.6875rem] font-semibold uppercase leading-snug tracking-[0.15em]',
        'text-white/50' => $onDark,
        'text-muted' => ! $onDark,
    ])>{{ $label }}</span>
</div>
