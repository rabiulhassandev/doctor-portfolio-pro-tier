{{--
    The heading block that opens most sections.

        <x-ui.section-heading
            eyebrow="What I treat"
            title="Cardiac care, start to finish"
            lead="From a first consultation through to long-term follow-up." />

    Centred by default because most sections on this site are; pass align="left"
    for the ones that are not. The measure is capped at ~34rem regardless — a
    lead paragraph running the full width of a desktop screen is unreadable no
    matter how nicely it is set.
--}}

@props([
    'eyebrow' => null,
    'title' => null,
    'lead' => null,
    // left | center
    'align' => 'center',
    // Renders as <h1> on a page where this is the main heading.
    'as' => 'h2',
])

@php
    $isCentred = $align === 'center';
@endphp

<div {{ $attributes->class([
    'flex flex-col gap-4',
    $isCentred ? 'items-center text-center' : 'items-start text-left',
]) }} data-reveal>
    @if ($eyebrow)
        <p class="eyebrow">{{ $eyebrow }}</p>
    @endif

    @if ($title)
        <{{ $as }} class="max-w-2xl text-3xl text-ink sm:text-4xl lg:text-[2.75rem]">
            {{ $title }}
        </{{ $as }}>
    @endif

    @if ($lead)
        <p class="max-w-[34rem] text-lg leading-relaxed text-muted">
            {{ $lead }}
        </p>
    @endif

    {{ $slot }}
</div>
