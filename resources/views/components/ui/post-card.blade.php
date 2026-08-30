{{--
    One article in the journal grid.
--}}

@props(['post'])

<x-ui.card :href="route('blog.show', $post)" padding="none"
           {{ $attributes->class('flex h-full flex-col') }}>

    @if ($cover = $post->coverUrl())
        <div class="aspect-[16/10] overflow-hidden bg-paper-shade">
            <img src="{{ $cover }}" alt="" loading="lazy"
                 class="size-full object-cover transition-transform duration-[900ms] ease-[cubic-bezier(0.16,1,0.3,1)] group-hover/card:scale-[1.04]">
        </div>
    @endif

    <div class="flex flex-1 flex-col gap-3 p-6">
        <div class="flex items-center gap-3 text-[0.6875rem] uppercase tracking-[0.12em] text-muted">
            @if ($post->published_at)
                <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('j M Y') }}</time>
                <span class="h-px w-4 bg-line-strong" aria-hidden="true"></span>
            @endif
            <span>{{ $post->readingMinutes() }} min</span>
        </div>

        <h3 class="text-xl leading-snug text-ink transition-colors duration-400 group-hover/card:text-brass">
            {{ $post->title }}
        </h3>

        <p class="line-clamp-2 text-[0.9375rem] leading-relaxed text-muted">{{ $post->summary(120) }}</p>

        <span class="mt-auto flex items-center gap-2 pt-3 text-[0.6875rem] font-semibold uppercase tracking-[0.14em] text-ink">
            Read
            <span class="transition-transform duration-400 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover/card:translate-x-1" aria-hidden="true">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </span>
        </span>
    </div>
</x-ui.card>
