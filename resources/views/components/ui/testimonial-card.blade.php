{{--
    A patient's words.

    Set in the display serif at a size that asks to be read, because a quote in
    the same size and face as the body copy around it is just more text. The
    stars are brass rather than highlighter-yellow — see `gold` in
    config/site.php.
--}}

@props(['testimonial'])

<x-ui.card {{ $attributes->class('flex h-full flex-col gap-5') }}>
    <x-ui.star-rating :rating="$testimonial->stars()" />

    <blockquote class="flex-1 font-display text-xl leading-snug text-ink">
        “{{ $testimonial->message }}”
    </blockquote>

    <figcaption class="flex items-center gap-3 border-t border-line pt-4">
        @if ($photo = $testimonial->photoUrl())
            <img src="{{ $photo }}" alt="" loading="lazy"
                 class="size-10 shrink-0 rounded-full object-cover">
        @else
            {{-- The usual case. The demo ships without patient photographs, and
                 initials in a circle look deliberate rather than missing. --}}
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full border border-line bg-paper-shade text-sm font-semibold text-muted"
                  aria-hidden="true">
                {{ $testimonial->initials() }}
            </span>
        @endif

        <span class="flex min-w-0 flex-col">
            <span class="truncate font-semibold text-ink">{{ $testimonial->name }}</span>
            @if ($testimonial->role)
                <span class="truncate text-sm text-muted">{{ $testimonial->role }}</span>
            @endif
        </span>
    </figcaption>
</x-ui.card>
