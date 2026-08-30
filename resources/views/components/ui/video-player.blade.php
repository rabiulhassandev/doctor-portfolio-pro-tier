{{--
    Plays a health video, wherever it lives.

        <x-ui.video-player :video="$video" />

    ===========================================================================
    THE CLICK-TO-LOAD FACADE
    ===========================================================================

    Nothing loads until the visitor presses play. Until then this is a poster
    image and a button.

    That is not a nicety. A YouTube <iframe> pulls roughly half a megabyte of
    player code and sets tracking cookies the instant it renders — so a grid of
    ten videos would cost five megabytes and ten trackers before anybody
    watched anything. On a phone on mobile data, which is how most patients here
    will arrive, that is the difference between a page that loads and one they
    abandon.

    It also means no third-party cookie is set until the visitor makes a
    deliberate choice, which is the right default on a page about somebody's
    illness.
--}}

@props([
    'video',
    // Autoplay once the facade is clicked. Off for the detail page hero, where
    // the visitor may just be reading.
    'autoplay' => true,
])

@php
    $thumbnail = $video->thumbnailUrl();
    $isEmbed = $video->isEmbed();
    $src = $video->embedUrl();

    // Only embeds take a query parameter; an uploaded file uses the `autoplay`
    // attribute instead.
    $embedSrc = $isEmbed && $autoplay
        ? $src . (str_contains($src, '?') ? '&' : '?') . 'autoplay=1'
        : $src;
@endphp

<div x-data="{ playing: false }"
     {{ $attributes->class('relative isolate aspect-video w-full overflow-hidden bg-night') }}>

    {{-- The poster --}}
    <button x-show="! playing"
            @click="playing = true"
            type="button"
            class="group absolute inset-0 flex size-full items-center justify-center"
            aria-label="Play “{{ $video->title }}”">

        @if ($thumbnail)
            <img src="{{ $thumbnail }}"
                 alt=""
                 loading="lazy"
                 class="absolute inset-0 size-full object-cover transition-transform duration-700 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-[1.03]">
        @else
            {{-- No thumbnail: a branded gradient built from config/site.php.
                 Looks deliberate; a broken image icon looks abandoned. --}}
            <span class="absolute inset-0"
                  class="bg-night-soft"
                  aria-hidden="true"></span>
        @endif

        {{-- A wash under the button so it stays legible on a bright frame. --}}
        <span class="absolute inset-0 bg-gradient-to-t from-night/70 via-night/20 to-transparent" aria-hidden="true"></span>

        <span class="relative flex size-16 items-center justify-center border border-white/60 bg-night/30 text-white backdrop-blur-sm transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:border-brass group-hover:text-brass sm:size-20"
              aria-hidden="true">
            <svg class="ml-1 size-7 sm:size-8" viewBox="0 0 24 24" fill="currentColor">
                <path d="M8 5.14v13.72a1 1 0 001.54.84l10.5-6.86a1 1 0 000-1.68L9.54 4.3A1 1 0 008 5.14z" />
            </svg>
        </span>

        @if ($duration = $video->formattedDuration())
            <span class="absolute bottom-3 right-3 rounded-md bg-night/80 px-2 py-1 text-xs font-semibold tabular-nums text-white">
                {{ $duration }}
            </span>
        @endif
    </button>

    {{-- The real player, created only once `playing` becomes true. --}}
    <template x-if="playing">
        @if ($isEmbed)
            <iframe src="{{ $embedSrc }}"
                    title="{{ $video->title }}"
                    class="absolute inset-0 size-full"
                    loading="lazy"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
        @else
            <video class="absolute inset-0 size-full bg-black"
                   controls
                   preload="metadata"
                   @if ($autoplay) autoplay @endif
                   @if ($thumbnail) poster="{{ $thumbnail }}" @endif>
                <source src="{{ $src }}" type="video/mp4">
                {{-- Shown by the browser when it cannot play the file at all. --}}
                <p class="p-4 text-white">
                    Your browser cannot play this video.
                    <a href="{{ $src }}" class="underline">Download it instead</a>.
                </p>
            </video>
        @endif
    </template>
</div>
