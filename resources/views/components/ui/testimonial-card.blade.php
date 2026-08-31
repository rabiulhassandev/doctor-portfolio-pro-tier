{{--
    A patient's words.

    ---------------------------------------------------------------------------
    NO CARD
    ---------------------------------------------------------------------------

    This used to be a white card with a border and a shadow, which is the wrong
    container for a quotation: a card says "here is an item", and three of them
    in a row says "here is a comparison table of our patients".

    So the chrome is gone. What is left is a brass hairline down the leading
    edge — the mark a printed page uses for a pulled quote — and the words
    themselves, set large in the display serif. The rule extends on hover, which
    is the only movement in the section.

    The quote is set in the serif because that is the point: a testimonial in
    the same face and size as the paragraph above it is just more text.
--}}

@props(['testimonial'])

<figure {{ $attributes->class('group/quote relative flex h-full flex-col gap-6 pl-6 sm:pl-8') }}>

    {{-- The rule. Two elements: a permanent faint one so the quote is anchored
         even at rest, and a solid brass one that draws down over it on hover. --}}
    <span class="absolute inset-y-0 left-0 w-px bg-line-strong" aria-hidden="true"></span>
    <span class="absolute inset-y-0 left-0 w-px origin-top scale-y-[0.18] bg-brass transition-transform duration-700 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover/quote:scale-y-100"
          aria-hidden="true"></span>

    <x-ui.star-rating :rating="$testimonial->stars()" />

    <blockquote class="flex-1 font-display text-[1.5rem] leading-[1.3] text-ink sm:text-[1.75rem]">
        {{ $testimonial->message }}
    </blockquote>

    <figcaption class="flex items-center gap-3.5">
        @if ($photo = $testimonial->photoUrl())
            <img src="{{ $photo }}" alt="" loading="lazy" class="size-9 shrink-0 rounded-full object-cover">
        @else
            {{-- The usual case. The demo ships without patient photographs, and
                 initials in a ruled square look deliberate. --}}
            <span class="flex size-9 shrink-0 items-center justify-center border border-line-strong text-[0.6875rem] font-semibold tracking-wider text-muted"
                  aria-hidden="true">{{ $testimonial->initials() }}</span>
        @endif

        <span class="flex min-w-0 flex-col">
            <span class="truncate text-sm font-semibold text-ink">{{ $testimonial->name }}</span>
            @if ($testimonial->role)
                <span class="truncate text-[0.6875rem] uppercase tracking-[0.12em] text-muted">{{ $testimonial->role }}</span>
            @endif
        </span>
    </figcaption>
</figure>
