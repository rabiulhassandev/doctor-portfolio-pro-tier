{{--
    One article in the journal grid.

    A magazine index entry rather than a card: the photograph, a hairline, the
    date and reading time, the headline. No border, no shadow, no panel — the
    picture is already a rectangle and wrapping it in a second one adds nothing
    but weight.

    The hairline under the image is what holds the grid together in the absence
    of card edges. It goes brass on hover, which is the whole hover state.
--}}

@props(['post'])

<a href="{{ route('blog.show', $post) }}"
   {{ $attributes->class('group/post flex h-full flex-col rounded-[2px] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brass') }}>

    @if ($cover = $post->coverUrl())
        <div class="aspect-[16/10] overflow-hidden bg-paper-shade">
            <img src="{{ $cover }}" alt="" loading="lazy"
                 class="size-full object-cover transition-transform duration-[900ms] ease-[cubic-bezier(0.16,1,0.3,1)] group-hover/post:scale-[1.04]">
        </div>
    @endif

    <span class="h-px w-full bg-line transition-colors duration-500 group-hover/post:bg-brass" aria-hidden="true"></span>

    <div class="flex flex-1 flex-col gap-2.5 pt-4">
        <div class="flex items-center gap-3 text-[0.6875rem] uppercase tracking-[0.12em] text-muted">
            @if ($post->published_at)
                <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('j M Y') }}</time>
                <span class="h-px w-4 bg-line-strong" aria-hidden="true"></span>
            @endif
            <span>{{ $post->readingMinutes() }} min read</span>
        </div>

        <h3 class="text-lg leading-snug text-ink transition-colors duration-400 group-hover/post:text-brass sm:text-xl">
            {{ $post->title }}
        </h3>

        <p class="line-clamp-2 text-[0.9375rem] leading-relaxed text-muted">{{ $post->summary(120) }}</p>
    </div>
</a>
