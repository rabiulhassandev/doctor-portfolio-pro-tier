{{--
    One figure in the credentials band: "20 years of practice".

    The number is set in the display serif at a size that carries across a
    section; the label underneath stays small and quiet so the figure does the
    work.
--}}

@props(['value', 'label'])

<div {{ $attributes->class('flex flex-col items-center gap-1 text-center') }}>
    <span class="font-display text-4xl leading-none text-brand tabular-nums sm:text-5xl">{{ $value }}</span>
    <span class="text-sm leading-snug text-muted">{{ $label }}</span>
</div>
