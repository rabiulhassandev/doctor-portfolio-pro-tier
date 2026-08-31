{{--
    One video, as a compact horizontal row.

    Used in the column beside the featured video on the home page. The grid card
    would work there too, but three of them stacked in a narrow column give you
    three large thumbnails and almost no words — which is the wrong balance when
    the point of the section is that these explain something.

    Thumbnail small and fixed-width, title given the room. Stays horizontal at
    every width: at 375px a 7rem thumbnail and two lines of title is still a
    comfortable row, and stacking it would make each item a third of the screen.
--}}

@props(['video'])

@php $thumbnail = $video->thumbnailUrl(); @endphp

<a href="{{ route('videos.show', $video) }}"
   {{ $attributes->class('row-editorial group/video flex items-start gap-4 px-3 py-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass sm:gap-5 sm:px-4 sm:py-5') }}>

    <div class="relative aspect-video w-28 shrink-0 overflow-hidden bg-night sm:w-32">
        @if ($thumbnail)
            <img src="{{ $thumbnail }}" alt="" loading="lazy"
                 class="size-full object-cover transition-transform duration-[900ms] ease-[cubic-bezier(0.16,1,0.3,1)] group-hover/video:scale-[1.06]">
        @endif

        <span class="absolute inset-0 flex items-center justify-center bg-night/25 text-white/90 transition-colors duration-500 group-hover/video:text-brass"
              aria-hidden="true">
            <svg class="ml-0.5 size-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M8 5.14v13.72a1 1 0 001.54.84l10.5-6.86a1 1 0 000-1.68L9.54 4.3A1 1 0 008 5.14z" />
            </svg>
        </span>
    </div>

    <div class="flex min-w-0 flex-1 flex-col gap-1.5">
        <div class="flex items-center gap-3 text-[0.625rem] font-semibold uppercase tracking-[0.14em]">
            @if ($video->topic)
                <span class="truncate text-brass">{{ $video->topic }}</span>
            @endif

            @if ($duration = $video->formattedDuration())
                <span class="shrink-0 tabular-nums text-muted">{{ $duration }}</span>
            @endif
        </div>

        <h3 class="line-clamp-2 text-base leading-snug text-ink transition-colors duration-400 group-hover/video:text-brass">
            {{ $video->title }}
        </h3>
    </div>
</a>
