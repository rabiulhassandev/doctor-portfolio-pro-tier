{{--
    One article in the blog grid.
--}}

@props(['post'])

<x-ui.card :href="route('blog.show', $post)" padding="none"
           {{ $attributes->class('group flex h-full flex-col') }}>

    @if ($cover = $post->coverUrl())
        <div class="aspect-[16/10] overflow-hidden bg-paper-shade">
            <img src="{{ $cover }}" alt="" loading="lazy"
                 class="size-full object-cover transition-transform duration-700 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-105">
        </div>
    @endif

    <div class="flex flex-1 flex-col gap-2 p-5">
        <div class="flex items-center gap-2 text-xs text-muted">
            @if ($post->published_at)
                <time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('j M Y') }}</time>
                <span aria-hidden="true">·</span>
            @endif
            <span>{{ $post->readingMinutes() }} min read</span>
        </div>

        <h3 class="text-lg leading-snug text-ink transition-colors group-hover:text-brand">{{ $post->title }}</h3>

        <p class="line-clamp-2 text-[0.9375rem] leading-relaxed text-muted">{{ $post->summary(120) }}</p>

        <span class="mt-auto flex items-center gap-1.5 pt-2 text-sm font-semibold text-brand">
            Read more
            <span class="transition-transform duration-300 group-hover:translate-x-0.5" aria-hidden="true">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                </svg>
            </span>
        </span>
    </div>
</x-ui.card>
