{{--
    The heading block that opens most sections.

        <x-ui.section-heading
            eyebrow="What I treat"
            title="Cardiac care, start to finish"
            lead="From a first consultation through to long-term follow-up." />

    LEFT-ALIGNED BY DEFAULT, which is the biggest single change from a
    centred-everything template. Centred headings are the safe choice and they
    are why so many sites feel interchangeable; a left edge gives the page a
    spine and lets the brass rule sit against something.

    The measure is capped regardless of alignment — a lead paragraph running
    the full width of a desktop screen is unreadable however nicely it is set.
--}}

@props([
    'eyebrow' => null,
    'title' => null,
    'lead' => null,
    // left | center
    'align' => 'left',
    // Set true on the dark sections.
    'onDark' => false,
    // Renders as <h1> where this is the page's main heading.
    'as' => 'h2',
])

@php
    $isCentred = $align === 'center';
@endphp

<div {{ $attributes->class([
    'flex flex-col gap-5',
    $isCentred ? 'items-center text-center' : 'items-start text-left',
]) }} data-reveal>
    @if ($eyebrow)
        <div class="flex items-center gap-3">
            <span class="rule-brass"></span>
            <p @class(['eyebrow', 'eyebrow-light' => $onDark])>{{ $eyebrow }}</p>
        </div>
    @endif

    @if ($title)
        <{{ $as }} @class([
            'max-w-3xl text-[2.5rem] leading-[1.05] sm:text-5xl lg:text-[3.5rem]',
            'text-white' => $onDark,
            'text-ink' => ! $onDark,
        ])>{{ $title }}</{{ $as }}>
    @endif

    @if ($lead)
        <p @class([
            'max-w-[36rem] text-lg leading-relaxed',
            'text-white/65' => $onDark,
            'text-muted' => ! $onDark,
        ])>{{ $lead }}</p>
    @endif

    {{ $slot }}
</div>
