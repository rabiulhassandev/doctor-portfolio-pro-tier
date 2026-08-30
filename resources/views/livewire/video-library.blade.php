<div class="flex flex-col gap-8">

    {{-- ---------------------------------------------------------------
         Filters
         --------------------------------------------------------------- --}}
    <div class="flex flex-col gap-5">

        {{-- Search --}}
        <div class="relative max-w-md">
            <label for="video-search" class="sr-only">Search the videos</label>

            <svg class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-muted"
                 fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>

            <input id="video-search"
                   type="search"
                   {{-- Debounced: firing a query on every keystroke would put
                        real load on shared hosting for no benefit. --}}
                   wire:model.live.debounce.400ms="search"
                   placeholder="Search by condition or title…"
                   class="w-full rounded-[3px] border border-line-strong bg-surface py-3 pl-11 pr-4 text-ink placeholder:text-muted/60 focus:border-brass focus:outline-2 focus:outline-offset-2 focus:outline-brass/40">

            <div wire:loading wire:target="search"
                 class="absolute right-4 top-1/2 -translate-y-1/2 text-muted">
                <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
                </svg>
            </div>
        </div>

        {{-- Topic chips. A horizontal strip rather than a dropdown: with five
             to fifteen topics, seeing them all is what makes the library feel
             browsable instead of searchable. --}}
        @if ($this->topics()->isNotEmpty())
            <div class="scrollbar-none -mx-1 flex gap-2 overflow-x-auto px-1 pb-1" role="group" aria-label="Filter by topic">
                <button type="button"
                        wire:click="$set('topic', '')"
                        @class([
                            'shrink-0 rounded-[3px] border px-4 py-2 text-sm font-semibold transition-colors',
                            'border-brass bg-night text-white' => $topic === '',
                            'border-line bg-surface text-ink hover:border-brass hover:text-brass' => $topic !== '',
                        ])>
                    All topics
                </button>

                @foreach ($this->topics() as $availableTopic)
                    <button type="button"
                            wire:key="topic-{{ Str::slug($availableTopic) }}"
                            wire:click="$set('topic', @js($availableTopic))"
                            @class([
                                'shrink-0 rounded-[3px] border px-4 py-2 text-sm font-semibold transition-colors',
                                'border-brass bg-night text-white' => $topic === $availableTopic,
                                'border-line bg-surface text-ink hover:border-brass hover:text-brass' => $topic !== $availableTopic,
                            ])>
                        {{ $availableTopic }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ---------------------------------------------------------------
         The grid
         --------------------------------------------------------------- --}}
    @if ($videos->isEmpty())
        <x-ui.empty-state
            :title="$search || $topic ? 'No videos match that' : 'No videos yet'"
            :description="$search || $topic
                ? 'Try a different search, or browse all the topics.'
                : 'Patient education videos will appear here.'"
            icon="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z">

            @if ($search || $topic)
                <x-ui.button wire:click="clearFilters" variant="secondary">Show all videos</x-ui.button>
            @endif
        </x-ui.empty-state>
    @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3"
             {{-- Dim briefly while a new page or filter loads, so the change is
                  visible rather than the grid appearing to freeze. --}}
             wire:loading.class="opacity-60"
             wire:target="search, topic, gotoPage, nextPage, previousPage">

            @foreach ($videos as $video)
                <x-ui.video-card :video="$video" wire:key="video-{{ $video->id }}" data-reveal="{{ 60 * ($loop->index % 3) }}" />
            @endforeach
        </div>

        @if ($videos->hasPages())
            <div class="pt-2">{{ $videos->links() }}</div>
        @endif
    @endif
</div>
