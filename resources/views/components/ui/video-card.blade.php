{{--
    One video in the library grid.

    Links through to the detail page rather than opening a player in place: a
    video about a condition usually needs its description beside it, and the
    detail page is the thing worth having in search results.
--}}

@props(['video'])

@php $thumbnail = $video->thumbnailUrl(); @endphp

<x-ui.card :href="route('videos.show', $video)" padding="none"
           {{ $attributes->class('flex h-full flex-col') }}>

    <div class="relative aspect-video overflow-hidden bg-night">
        @if ($thumbnail)
            <img src="{{ $thumbnail }}" alt="" loading="lazy"
                 class="size-full object-cover transition-transform duration-[900ms] ease-[cubic-bezier(0.16,1,0.3,1)] group-hover/card:scale-[1.04]">
        @else
            {{-- No thumbnail: a branded field rather than a broken image icon. --}}
            <span class="absolute inset-0 bg-night-soft" aria-hidden="true"></span>
        @endif

        <span class="absolute inset-0 bg-gradient-to-t from-night/75 via-night/10 to-transparent" aria-hidden="true"></span>

        {{-- Play mark: a brass-ruled square, matching the wordmark. --}}
        <span class="absolute left-1/2 top-1/2 flex size-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center border border-white/50 bg-night/35 text-white backdrop-blur-sm transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover/card:border-brass group-hover/card:text-brass"
              aria-hidden="true">
            <svg class="ml-0.5 size-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M8 5.14v13.72a1 1 0 001.54.84l10.5-6.86a1 1 0 000-1.68L9.54 4.3A1 1 0 008 5.14z" />
            </svg>
        </span>

        @if ($duration = $video->formattedDuration())
            <span class="absolute bottom-3 right-3 bg-night/85 px-2 py-0.5 text-[0.6875rem] font-semibold tabular-nums text-white">
                {{ $duration }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-6">
        @if ($video->topic)
            <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.14em] text-brass">{{ $video->topic }}</p>
        @endif

        <h3 class="text-xl leading-snug text-ink transition-colors duration-400 group-hover/card:text-brass">
            {{ $video->title }}
        </h3>

        @if ($summary = $video->summary(110))
            <p class="line-clamp-2 text-[0.9375rem] leading-relaxed text-muted">{{ $summary }}</p>
        @endif
    </div>
</x-ui.card>
