{{--
    One video in the library grid.

        <x-ui.video-card :video="$video" />

    Links through to the detail page rather than opening a player in place. That
    is deliberate: a video about a condition usually needs the description
    beside it, and the detail page is the thing worth having in search results.
    The lightbox lives on the home page, where there is no room for a detail
    view.
--}}

@props(['video'])

@php
    $thumbnail = $video->thumbnailUrl();
@endphp

<x-ui.card :href="route('videos.show', $video)" padding="none"
           {{ $attributes->class('group flex h-full flex-col') }}>

    {{-- Poster --}}
    <div class="relative aspect-video overflow-hidden bg-ink-deep">
        @if ($thumbnail)
            <img src="{{ $thumbnail }}"
                 alt=""
                 loading="lazy"
                 class="size-full object-cover transition-transform duration-700 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-105">
        @else
            <span class="absolute inset-0"
                  style="background: linear-gradient(135deg, var(--brand-primary), var(--brand-accent));"
                  aria-hidden="true"></span>
        @endif

        <span class="absolute inset-0 bg-gradient-to-t from-ink-deep/55 to-transparent opacity-70 transition-opacity duration-500 group-hover:opacity-90" aria-hidden="true"></span>

        {{-- Play badge --}}
        <span class="absolute left-1/2 top-1/2 flex size-12 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white/92 text-brand shadow-lift transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-110"
              aria-hidden="true">
            <svg class="ml-0.5 size-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M8 5.14v13.72a1 1 0 001.54.84l10.5-6.86a1 1 0 000-1.68L9.54 4.3A1 1 0 008 5.14z" />
            </svg>
        </span>

        @if ($duration = $video->formattedDuration())
            <span class="absolute bottom-2.5 right-2.5 rounded-md bg-ink-deep/80 px-2 py-0.5 text-xs font-semibold tabular-nums text-white">
                {{ $duration }}
            </span>
        @endif
    </div>

    {{-- Text --}}
    <div class="flex flex-1 flex-col gap-2 p-5">
        @if ($video->topic)
            <x-ui.badge tone="accent" class="w-fit">{{ $video->topic }}</x-ui.badge>
        @endif

        <h3 class="text-lg leading-snug text-ink transition-colors group-hover:text-brand">
            {{ $video->title }}
        </h3>

        @if ($summary = $video->summary(110))
            <p class="line-clamp-2 text-[0.9375rem] leading-relaxed text-muted">{{ $summary }}</p>
        @endif

        <span class="mt-auto flex items-center gap-1.5 pt-2 text-sm font-semibold text-brand">
            Watch
            <span class="transition-transform duration-300 group-hover:translate-x-0.5" aria-hidden="true">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </span>
        </span>
    </div>
</x-ui.card>
