{{--
    One cell of the figures ledger: "Years in practice — 18+".

        <x-ui.stat value="18+" label="Years in practice" />

    ---------------------------------------------------------------------------
    LABEL ABOVE, FIGURE BELOW
    ---------------------------------------------------------------------------

    The other way round — number first, caption under it — is what every
    template does, and it makes each cell read as a badge. Putting the small
    letterspaced label on top and hanging the figure off it reads as a table of
    accounts, which is both quieter and more convincing: a page that states its
    numbers plainly looks like it is not trying to sell them.

    The cells are meant to sit inside a divided container — see the ledger on
    the home page — so this component carries no border of its own.
--}}

@props([
    'value',
    'label',
    // Set true on the dark sections.
    'onDark' => false,
])

<div {{ $attributes->class('flex flex-col gap-2 px-4 py-5 sm:gap-3 sm:px-7 sm:py-9') }}>
    <span @class([
        'eyebrow',
        'eyebrow-light' => $onDark,
    ])>{{ $label }}</span>

    <span @class([
        'font-display text-[2.25rem] leading-none tabular-nums sm:text-5xl lg:text-[3.25rem]',
        'text-white' => $onDark,
        'text-ink' => ! $onDark,
    ])>{{ $value }}</span>
</div>
