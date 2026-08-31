@php
    use App\Support\Media;
@endphp

<x-layouts.app
    :title="$post->meta_title ?: $post->title"
    :description="$post->meta_description ?: $post->summary(155)"
    :image="$post->cover_image">

    {{-- schema.org Article, so the piece can appear as a rich result with a
         date and an author rather than as a bare blue link. --}}
    @push('schema')
        <script type="application/ld+json">
            {{-- json_encode rather than the @json directive: Blade's directive
                 parser cannot follow a multi-line array literal. --}}
            {!! json_encode(array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $post->title,
                'description' => $post->summary(200),
                'image' => Media::absoluteUrl($post->cover_image),
                'datePublished' => $post->published_at?->toAtomString(),
                'dateModified' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
                'author' => [
                    '@type' => 'Person',
                    'name' => $doctor->name,
                    'jobTitle' => $doctor->specialization,
                    'url' => route('about'),
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $doctor->chamber_name ?: $doctor->name,
                ],
                'mainEntityOfPage' => route('blog.show', $post),
            ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endpush

    <div class="paper-grain relative isolate bg-paper">
    <article class="mx-auto max-w-3xl px-5 py-10 sm:px-8 sm:py-14">

        <nav class="mb-6 flex items-center gap-2 text-sm text-muted" aria-label="Breadcrumb">
            <a href="{{ route('blog.index') }}" class="link-underline hover:text-brass">Articles</a>
            <span aria-hidden="true">/</span>
            <span class="truncate text-ink">{{ $post->title }}</span>
        </nav>

        <header class="flex flex-col gap-4" data-reveal>
            <h1 class="text-4xl text-ink sm:text-5xl">{{ $post->title }}</h1>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted">
                @if ($post->published_at)
                    <time datetime="{{ $post->published_at->toDateString() }}">
                        {{ $post->published_at->format('j F Y') }}
                    </time>
                @endif
                <span>{{ $post->readingMinutes() }} min read</span>
                <span>By {{ $doctor->name }}</span>
            </div>
        </header>

        @if ($cover = $post->coverUrl())
            <img src="{{ $cover }}"
                 alt=""
                 class="mt-8 aspect-[16/9] w-full rounded-[4px] object-cover shadow-lift"
                 data-reveal="80">
        @endif

        {{--
            Rendered unescaped because it comes from the site owner's own rich
            text editor in the admin panel. NEVER pipe visitor-submitted content
            through here.
        --}}
        <div class="prose-article mt-10" data-reveal>
            {!! $post->content !!}
        </div>

        <footer class="mt-12 flex flex-col gap-6 border-t border-line pt-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <a href="{{ route('blog.index') }}" class="link-underline font-semibold text-ink">
                    ← All articles
                </a>

                @if (config('site.features.booking'))
                    <x-ui.button :href="route('booking')">Book an appointment</x-ui.button>
                @endif
            </div>

            <p class="border-l border-brass bg-paper-shade p-4 text-[0.9375rem] leading-relaxed text-muted">
                This article is general information, not advice about your own health.
                Please speak to {{ $doctor->name }} about anything that concerns you.
            </p>
        </footer>
    </article>
    </div>

    @if ($related->isNotEmpty())
        <section class="paper-grain relative isolate border-t border-line bg-paper-shade">
            <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8">
                <x-ui.section-heading align="left" eyebrow="Keep reading" title="More articles" class="mb-8" />

                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-3 lg:gap-x-8">
                    @foreach ($related as $other)
                        <x-ui.post-card :post="$other" data-reveal="{{ 50 * $loop->index }}" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.app>
