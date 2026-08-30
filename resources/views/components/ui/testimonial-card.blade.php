{{--
    A patient's words.

    Set large in the display serif, which is the whole point: a quote in the
    same size and face as the body copy around it is just more text. The
    opening quotation mark is oversized and brass, sitting behind the words as
    a mark rather than as punctuation.
--}}

@props(['testimonial'])

<x-ui.card {{ $attributes->class('flex h-full flex-col gap-6') }}>
    <div class="flex items-start justify-between gap-4">
        <span class="font-display text-6xl leading-[0.6] text-brass/35" aria-hidden="true">&ldquo;</span>
        <x-ui.star-rating :rating="$testimonial->stars()" />
    </div>

    <blockquote class="flex-1 font-display text-2xl leading-[1.25] text-ink">
        {{ $testimonial->message }}
    </blockquote>

    <figcaption class="flex items-center gap-3.5 border-t border-line pt-5">
        @if ($photo = $testimonial->photoUrl())
            <img src="{{ $photo }}" alt="" loading="lazy" class="size-10 shrink-0 object-cover">
        @else
            {{-- The usual case. The demo ships without patient photographs,
                 and initials in a ruled square look deliberate. --}}
            <span class="flex size-10 shrink-0 items-center justify-center border border-line-strong text-xs font-semibold tracking-wider text-muted"
                  aria-hidden="true">{{ $testimonial->initials() }}</span>
        @endif

        <span class="flex min-w-0 flex-col">
            <span class="truncate text-sm font-semibold text-ink">{{ $testimonial->name }}</span>
            @if ($testimonial->role)
                <span class="truncate text-xs uppercase tracking-[0.1em] text-muted">{{ $testimonial->role }}</span>
            @endif
        </span>
    </figcaption>
</x-ui.card>
