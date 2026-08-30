@php
    use App\Support\Media;
@endphp

<x-layouts.app
    :title="$video->meta_title ?: $video->title"
    :description="$video->meta_description ?: $video->summary(155)"
    :image="$video->thumbnail_path">

    {{-- schema.org VideoObject.
         This is what puts a video thumbnail beside the result in Google, which
         on a page competing with a hundred others is most of the battle. --}}
    @push('schema')
        <script type="application/ld+json">
            {{-- json_encode rather than the @json directive: Blade's directive
                 parser cannot follow a multi-line array literal. --}}
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'VideoObject',
                'name' => $video->title,
                'description' => $video->summary(300) ?: $video->title,
                'thumbnailUrl' => array_values(array_filter([
                    $video->thumbnail_path
                        ? Media::absoluteUrl($video->thumbnail_path)
                        : $video->thumbnailUrl(),
                ])),
                'uploadDate' => ($video->published_at ?? $video->created_at)?->toAtomString(),
                'duration' => $video->iso8601Duration(),
                'contentUrl' => $video->isEmbed() ? $video->watchUrl() : Media::absoluteUrl($video->video_path),
                'embedUrl' => $video->embedUrl(),
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $doctor->chamber_name ?: $doctor->name,
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <article class="mx-auto max-w-4xl px-5 py-10 sm:px-8 sm:py-14">

        {{-- Breadcrumb --}}
        <nav class="mb-6 flex items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
            <a href="{{ route('videos.index') }}" class="link-underline hover:text-brand">Health videos</a>
            <span aria-hidden="true">/</span>
            <span class="truncate text-ink">{{ $video->title }}</span>
        </nav>

        <div class="flex flex-col gap-6" data-reveal>
            {{-- The player does not load until it is pressed. --}}
            <x-ui.video-player :video="$video" />

            <div class="flex flex-col gap-3">
                @if ($video->topic)
                    <x-ui.badge tone="accent" class="w-fit">{{ $video->topic }}</x-ui.badge>
                @endif

                <h1 class="text-3xl text-ink sm:text-4xl">{{ $video->title }}</h1>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted">
                    @if ($video->published_at)
                        <time datetime="{{ $video->published_at->toDateString() }}">
                            {{ $video->published_at->format('j F Y') }}
                        </time>
                    @endif

                    @if ($duration = $video->formattedDuration())
                        <span>{{ $duration }}</span>
                    @endif

                    @if ($watchUrl = $video->watchUrl())
                        <a href="{{ $watchUrl }}" target="_blank" rel="noopener noreferrer"
                           class="link-underline hover:text-brand">
                            Watch on {{ Str::headline($video->video_type->value) }}
                        </a>
                    @endif
                </div>
            </div>

            @if ($video->description)
                <div class="prose-article max-w-none">
                    {!! nl2br(e($video->description)) !!}
                </div>
            @endif

            {{-- Medical disclaimer. Worth stating plainly on any page where a
                 patient might mistake general information for advice about
                 their own case. --}}
            <p class="rounded-xl border border-line bg-paper-shade p-4 text-[0.9375rem] leading-relaxed text-muted">
                This video is general information, not advice about your own health.
                Please speak to {{ $doctor->name }} about anything that concerns you.
            </p>

            @if (config('site.features.booking'))
                <div class="flex flex-wrap gap-3">
                    <x-ui.button :href="route('booking')">Book an appointment</x-ui.button>
                    <x-ui.button :href="route('videos.index')" variant="secondary">More videos</x-ui.button>
                </div>
            @endif
        </div>
    </article>

    {{-- Related --}}
    @if ($related->isNotEmpty())
        <section class="border-t border-line bg-paper-shade">
            <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8">
                <x-ui.section-heading
                    align="left"
                    eyebrow="More on this topic"
                    :title="'Other videos about ' . $video->topic"
                    class="mb-8" />

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $other)
                        <x-ui.video-card :video="$other" data-reveal="{{ 60 * $loop->index }}" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.app>
