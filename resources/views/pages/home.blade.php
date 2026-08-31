@php
    use App\Support\Media;

    $photo = Media::url($doctor->photo);
    $status = $doctor->openStatus();
    $bookingEnabled = config('site.features.booking');
@endphp

<x-layouts.app>

    <x-site.physician-schema />

    {{-- =================================================================
         Hero — near full height, dark, photographic
         ================================================================= --}}
    <section class="surface-grain relative isolate flex min-h-[88svh] items-center overflow-hidden bg-night">
        {{-- The slow drifting light. Far too slow to read as an animation; it
             exists so the largest dark area on the site is not a flat
             rectangle. Stopped entirely under prefers-reduced-motion. --}}
        <div class="hero-glow -z-10" aria-hidden="true"></div>

        {{-- The extra bottom padding at `lg` is not decoration: it is the room
             the figures ledger below hangs up into. Reduce one and the card
             lands on the portrait's brass frame. --}}
        <div class="mx-auto grid w-full max-w-7xl gap-10 px-5 pb-16 pt-28 sm:gap-14 sm:px-8 sm:pb-24 sm:pt-40 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:gap-20 lg:pb-36">

            {{-- Copy --}}
            <div class="flex flex-col items-start gap-7 sm:gap-8">
                <div class="flex flex-col gap-5 sm:gap-6" data-reveal>
                    <div class="flex items-center gap-3">
                        <span class="rule-brass"></span>
                        <p class="eyebrow eyebrow-light">{{ $doctor->specialization }}</p>
                    </div>

                    {{-- 40px at 375px, not 52. The old value took four lines for
                         the seeded tagline and pushed the buttons off screen. --}}
                    <h1 class="max-w-2xl text-[2.5rem] leading-[1.04] text-white sm:text-6xl lg:text-[4.5rem]">
                        {{ $doctor->tagline ?: 'Careful, unhurried heart care.' }}
                    </h1>

                    @if ($doctor->short_bio)
                        <p class="max-w-lg leading-relaxed text-white/60 sm:text-lg">{{ $doctor->short_bio }}</p>
                    @endif
                </div>

                {{-- Stacked and full width on a phone. Side by side, two
                     uppercase labels at 390px each wrap onto two lines and the
                     pair reads as a cramped block rather than as two choices. --}}
                <div class="flex w-full flex-col items-stretch gap-3 sm:w-auto sm:flex-row sm:items-center sm:gap-4" data-reveal="120">
                    @if ($bookingEnabled)
                        <x-ui.button :href="route('booking')" size="lg">Book a consultation</x-ui.button>
                    @endif

                    @if ($doctor->telHref())
                        <x-ui.button :href="$doctor->telHref()" variant="outline-light" size="lg">
                            {{ $doctor->phone }}
                        </x-ui.button>
                    @endif
                </div>

                {{-- Open/closed and registration, on one quiet line. Both are
                     things a patient here checks before anything else. --}}
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm" data-reveal="180">
                    @if ($status)
                        <span class="flex items-center gap-2">
                            <span class="relative flex size-1.5">
                                @if ($status['is_open'])
                                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-positive opacity-70 motion-reduce:hidden"></span>
                                @endif
                                <span class="relative inline-flex size-1.5 rounded-full {{ $status['is_open'] ? 'bg-positive' : 'bg-white/40' }}"></span>
                            </span>
                            <span class="{{ $status['is_open'] ? 'text-positive' : 'text-white/50' }}">{{ $status['label'] }}</span>
                            <span class="text-white/35">{{ $status['detail'] }}</span>
                        </span>
                    @endif

                    @if ($registration = $doctor->registration())
                        <span class="text-white/35">{{ $registration }}</span>
                    @endif
                </div>
            </div>

            {{-- Portrait --}}
            <div class="relative" data-reveal="100">
                @if ($photo)
                    {{-- A brass frame offset behind the photograph. One line and
                         one shadow; it is the cheapest way to make a stock
                         portrait look placed rather than pasted. --}}
                    <div class="absolute -bottom-4 -right-4 hidden size-full border border-brass/40 sm:block" aria-hidden="true"></div>

                    <img src="{{ $photo }}"
                         alt="{{ $doctor->name }}, {{ $doctor->specialization }}"
                         class="relative aspect-[4/5] w-full object-cover shadow-float">

                    @if ($doctor->years_of_experience)
                        {{-- Solid, not `.glass`. A translucent panel has to be
                             legible over WHATEVER photograph the doctor
                             uploads, and over a white studio wall the glass
                             went pale grey and took the white type with it.
                             Near-opaque night with a brass hairline works over
                             anything. --}}
                        <div class="absolute bottom-5 left-0 flex -translate-x-2 items-baseline gap-3 border border-brass/40 bg-night/92 px-4 py-3 backdrop-blur-sm sm:bottom-6 sm:-translate-x-4 sm:px-5 sm:py-4">
                            <span class="font-display text-3xl leading-none text-brass sm:text-4xl">{{ $doctor->years_of_experience }}</span>
                            <span class="text-[0.6875rem] font-semibold uppercase leading-tight tracking-[0.14em] text-white/70">
                                years in<br>practice
                            </span>
                        </div>
                    @endif
                @else
                    {{-- No photograph uploaded yet. A branded panel rather than
                         a grey box with a broken image icon. --}}
                    <div class="flex aspect-[4/5] items-center justify-center border border-brass/30 bg-night-soft">
                        <span class="font-display text-8xl text-brass/60">
                            {{ Str::of($doctor->name)->replaceMatches('/^Dr\.?\s*/i', '')->explode(' ')->filter()->take(2)->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->implode('') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- =================================================================
         The ledger
         -----------------------------------------------------------------
         The figures, set as a table of accounts and lifted so it straddles
         the seam between the dark hero and the light page. That overlap is
         what stitches the two halves of the site together — without it the
         hero stops and a different website starts.

         Desktop only. On a phone there is no room for a card to hang off an
         edge, and a negative margin at that width just clips the hero.
         ================================================================= --}}
    @php
        $stats = collect([
            $doctor->years_of_experience ? [$doctor->years_of_experience.'+', 'Years in practice'] : null,
            $services->isNotEmpty() ? [$services->count().'+', 'Services offered'] : null,
            $doctor->qualifications ? [count($doctor->qualifications), 'Qualifications'] : null,
            $testimonials->isNotEmpty() ? [$testimonials->count().'+', 'Patient reviews'] : null,
        ])->filter()->values();
    @endphp

    @if ($stats->isNotEmpty())
        <section class="paper-grain relative isolate bg-paper">
            <div class="mx-auto max-w-7xl px-5 pb-14 pt-12 sm:px-8 sm:pb-20 lg:-mt-16 lg:pt-0">
                {{-- Two across even on a phone. Stacked one per row it ran to
                     five hundred pixels of scrolling for four numbers, and a
                     ledger you cannot see at once is not a ledger. --}}
                <div class="grid grid-cols-2 border border-line bg-surface shadow-card lg:grid-cols-4" data-reveal>
                    @foreach ($stats as [$value, $label])
                        {{-- The rules are drawn per-cell rather than with
                             `divide-*`, which only knows about one axis at a
                             time — and a 2×2 needs both. --}}
                        <x-ui.stat
                            :value="$value"
                            :label="$label"
                            @class([
                                'border-line',
                                'border-t lg:border-t-0' => $loop->index >= 2,
                                'border-l' => $loop->index % 2 === 1,
                                'lg:border-l' => $loop->index > 0,
                            ]) />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- =================================================================
         Services — an editorial list, not a card grid
         ================================================================= --}}
    @if ($services->isNotEmpty())
        <section class="paper-grain relative isolate bg-paper">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-24">
                <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end lg:gap-12">
                    <x-ui.section-heading
                        eyebrow="What I treat"
                        title="Care for every stage"
                        lead="From a first consultation through to long-term follow-up." />

                    <x-ui.button :href="route('services')" variant="outline" class="shrink-0 max-sm:w-full" data-reveal="100">
                        All services
                    </x-ui.button>
                </div>

                {{-- Two columns of rows on a wide screen rather than one very
                     long list — a single column of six rows on a 1440px monitor
                     leaves two thirds of the width doing nothing. --}}
                <div class="mt-10 grid border-t border-line sm:mt-14 lg:grid-cols-2 lg:gap-x-12">
                    @foreach ($services as $service)
                        <x-ui.service-row
                            :service="$service"
                            :index="$loop->index"
                            class="border-b border-line"
                            data-reveal="{{ 60 * ($loop->index % 3) }}" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- =================================================================
         About — dark, qualifications as a numbered list
         ================================================================= --}}
    <section class="surface-grain relative isolate overflow-hidden bg-night">
        <div class="pointer-events-none absolute inset-0 -z-10"
             style="background: radial-gradient(40rem 26rem at 88% 10%, color-mix(in oklab, var(--brand-brass) 12%, transparent), transparent 70%);"
             aria-hidden="true"></div>

        <div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:gap-14 sm:px-8 sm:py-24 lg:grid-cols-2 lg:gap-20">
            <div class="flex flex-col gap-6 sm:gap-7">
                <x-ui.section-heading
                    on-dark
                    eyebrow="About"
                    :title="'A word from ' . $doctor->name" />

                @php
                    /*
                     | The first paragraph, cut on a word boundary.
                     |
                     | A plain character limit ended this block on "If I have not
                     | been clear, pl…" — a sentence sliced through a word, which
                     | on the one section that is meant to sound like the doctor
                     | talking is worse than no text at all. Taking the opening
                     | paragraph whole is also simply better writing: it is a
                     | complete thought, which an arbitrary 320 characters never
                     | is.
                     */
                    $intro = Str::of($doctor->philosophy ?: strip_tags($doctor->bio ?? ''))
                        ->trim()
                        ->explode("\n\n")
                        ->first() ?? '';
                    $intro = Str::limit(trim($intro), 300, preserveWords: true);
                @endphp

                @if ($intro !== '')
                    <p class="max-w-lg leading-relaxed text-white/60 sm:text-lg" data-reveal>{{ $intro }}</p>
                @endif

                <x-ui.button :href="route('about')" variant="outline-light" class="w-fit" data-reveal="80">
                    Read more
                </x-ui.button>
            </div>

            @if ($doctor->qualifications)
                <ul class="flex flex-col divide-y divide-night-line border-y border-night-line" data-reveal="120">
                    @foreach (array_slice($doctor->qualifications, 0, 4) as $qualification)
                        <li class="flex items-baseline gap-5 py-5 sm:gap-6">
                            <span class="font-display text-2xl leading-none text-brass/60" aria-hidden="true">
                                0{{ $loop->iteration }}
                            </span>

                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="font-semibold text-white sm:text-lg">{{ $qualification['title'] ?? '' }}</span>
                                <span class="text-sm text-white/45">
                                    {{ collect([$qualification['institution'] ?? null, $qualification['year'] ?? null])->filter()->implode(' · ') }}
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- =================================================================
         Health videos — one lead film, the rest as a list beside it
         ================================================================= --}}
    @if ($videos->isNotEmpty())
        @php
            $leadVideo = $videos->first();
            $otherVideos = $videos->skip(1);
        @endphp

        <section class="paper-grain relative isolate bg-paper">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-24">
                <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end lg:gap-12">
                    <x-ui.section-heading
                        eyebrow="Patient education"
                        title="Understand your condition"
                        lead="Short films explaining what is happening, in plain language." />

                    <x-ui.button :href="route('videos.index')" variant="outline" class="shrink-0 max-sm:w-full" data-reveal="100">
                        The library
                    </x-ui.button>
                </div>

                <div class="mt-10 grid gap-8 sm:mt-14 lg:grid-cols-[1.35fr_1fr] lg:gap-12">
                    <x-ui.video-card :video="$leadVideo" feature data-reveal />

                    @if ($otherVideos->isNotEmpty())
                        {{-- `self-start`, or the grid stretches this box to the
                             height of the feature beside it and the closing
                             hairline floats hundreds of pixels below the last
                             row with nothing in between. --}}
                        <div class="flex flex-col divide-y divide-line self-start border-y border-line"
                             data-reveal="120">
                            @foreach ($otherVideos as $video)
                                <x-ui.video-row :video="$video" />
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- =================================================================
         Testimonials
         ================================================================= --}}
    @if ($testimonials->isNotEmpty())
        <section class="paper-grain relative isolate border-y border-line bg-paper-shade">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-24">
                <x-ui.section-heading
                    eyebrow="In their words"
                    title="What patients say"
                    class="mb-10 sm:mb-14" />

                <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-3 lg:gap-12">
                    @foreach ($testimonials->take(3) as $testimonial)
                        <x-ui.testimonial-card :testimonial="$testimonial" data-reveal="{{ 70 * $loop->index }}" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- =================================================================
         Journal
         ================================================================= --}}
    @if ($posts->isNotEmpty())
        <section class="paper-grain relative isolate bg-paper">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-24">
                <div class="flex flex-col justify-between gap-6 lg:flex-row lg:items-end lg:gap-12">
                    <x-ui.section-heading eyebrow="Reading" title="From the practice" />

                    <x-ui.button :href="route('blog.index')" variant="outline" class="shrink-0 max-sm:w-full" data-reveal="100">
                        All articles
                    </x-ui.button>
                </div>

                <div class="mt-10 grid gap-10 sm:mt-14 sm:grid-cols-2 lg:grid-cols-3 lg:gap-8">
                    @foreach ($posts as $post)
                        <x-ui.post-card :post="$post" data-reveal="{{ 70 * $loop->index }}" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- The closing call to action lives in the footer, which is dark and
         already carries it. A second one here would be the same ask twice. --}}
</x-layouts.app>
