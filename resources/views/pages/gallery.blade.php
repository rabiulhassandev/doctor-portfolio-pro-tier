{{--
    The gallery, with a lightbox.

    Alpine holds one index for the whole grid rather than an open flag per
    image, which is what makes the arrow keys work: next and previous are just
    arithmetic on that index. `x-trap` keeps the keyboard inside the lightbox
    while it is open, so tabbing does not silently walk off into the page
    behind it.
--}}

<x-layouts.app
    title="Gallery"
    :description="'Photographs of the chamber and facilities at ' . ($doctor->chamber_name ?: $doctor->name) . '.'">

    <x-ui.page-hero
        eyebrow="The chamber"
        title="Gallery"
        lead="A look at where you will be seen, before you arrive." />

    <section class="paper-grain relative isolate bg-paper">
    <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20">
        @if ($images->isEmpty())
            <x-ui.empty-state
                title="No photographs yet"
                description="Pictures of the chamber will appear here."
                icon="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
        @else
            <div x-data="{
                    open: null,
                    images: {{ Illuminate\Support\Js::from($images->map(fn ($image) => [
                        'src' => $image->imageUrl(),
                        'alt' => $image->altText(),
                        'caption' => $image->caption,
                    ])->values()) }},
                    next() { this.open = (this.open + 1) % this.images.length },
                    previous() { this.open = (this.open - 1 + this.images.length) % this.images.length },
                 }">

                {{-- The grid --}}
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
                    @foreach ($images as $index => $image)
                        <button type="button"
                                @click="open = {{ $index }}"
                                class="group relative aspect-square overflow-hidden border border-line bg-paper-shade focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-brass"
                                data-reveal="{{ 40 * ($index % 4) }}">
                            <img src="{{ $image->imageUrl() }}"
                                 alt="{{ $image->altText() }}"
                                 loading="lazy"
                                 class="size-full object-cover transition-transform duration-700 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-105">

                            <span class="absolute inset-0 bg-night/0 transition-colors duration-300 group-hover:bg-night/20" aria-hidden="true"></span>

                            @if ($image->caption)
                                <span class="absolute inset-x-0 bottom-0 translate-y-full bg-gradient-to-t from-night/85 to-transparent p-3 text-left text-xs text-white transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:translate-y-0">
                                    {{ $image->caption }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- The lightbox --}}
                <div x-cloak
                     x-show="open !== null"
                     x-trap.noscroll="open !== null"
                     @keydown.escape.window="open = null"
                     @keydown.arrow-right.window="next()"
                     @keydown.arrow-left.window="previous()"
                     class="fixed inset-0 z-[60] flex items-center justify-center"
                     role="dialog"
                     aria-modal="true"
                     aria-label="Photograph">

                    <div x-show="open !== null"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         @click="open = null"
                         class="absolute inset-0 bg-night/92 backdrop-blur-sm"></div>

                    <figure x-show="open !== null"
                            x-transition:enter="transition ease-[cubic-bezier(0.16,1,0.3,1)] duration-300"
                            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                            class="relative z-10 flex max-h-[88vh] w-[min(64rem,92vw)] flex-col items-center gap-4">

                        <img x-show="open !== null"
                             :src="images[open]?.src"
                             :alt="images[open]?.alt"
                             class="max-h-[74vh] w-auto rounded-[3px] object-contain shadow-float">

                        <figcaption x-show="images[open]?.caption" x-text="images[open]?.caption"
                                    class="text-center text-sm text-white/80"></figcaption>
                    </figure>

                    {{-- Controls --}}
                    <button type="button" @click="open = null"
                            class="absolute right-4 top-4 z-20 flex size-11 items-center justify-center rounded-[3px] bg-white/10 text-white backdrop-blur transition-colors hover:bg-white/20"
                            aria-label="Close">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <template x-if="images.length > 1">
                        <div>
                            <button type="button" @click="previous()"
                                    class="absolute left-3 top-1/2 z-20 flex size-11 -translate-y-1/2 items-center justify-center rounded-[3px] bg-white/10 text-white backdrop-blur transition-colors hover:bg-white/20 sm:left-6"
                                    aria-label="Previous photograph">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                                </svg>
                            </button>

                            <button type="button" @click="next()"
                                    class="absolute right-3 top-1/2 z-20 flex size-11 -translate-y-1/2 items-center justify-center rounded-[3px] bg-white/10 text-white backdrop-blur transition-colors hover:bg-white/20 sm:right-6"
                                    aria-label="Next photograph">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        @endif
    </div>
    </section>
</x-layouts.app>
