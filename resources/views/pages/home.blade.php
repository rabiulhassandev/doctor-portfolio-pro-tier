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
    <section class="surface-grain relative isolate flex min-h-[92svh] items-center overflow-hidden bg-night">
        {{-- The slow drifting light. Far too slow to read as an animation; it
             exists so the largest dark area on the site is not a flat
             rectangle. Stopped entirely under prefers-reduced-motion. --}}
        <div class="hero-glow -z-10" aria-hidden="true"></div>

        <div class="mx-auto grid w-full max-w-7xl gap-14 px-5 pb-20 pt-32 sm:px-8 sm:pb-24 sm:pt-40 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:gap-20">

            {{-- Copy --}}
            <div class="flex flex-col items-start gap-8">
                <div class="flex flex-col gap-6" data-reveal>
                    <div class="flex items-center gap-3">
                        <span class="rule-brass"></span>
                        <p class="eyebrow eyebrow-light">{{ $doctor->specialization }}</p>
                    </div>

                    <h1 class="max-w-2xl text-[3.25rem] leading-[1.02] text-white sm:text-7xl lg:text-[5rem]">
                        {{ $doctor->tagline ?: 'Careful, unhurried heart care.' }}
                    </h1>

                    @if ($doctor->short_bio)
                        <p class="max-w-lg text-lg leading-relaxed text-white/60">{{ $doctor->short_bio }}</p>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-4" data-reveal="120">
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
                        <div class="glass absolute bottom-6 left-0 flex -translate-x-4 items-baseline gap-3 px-5 py-4">
                            <span class="font-display text-4xl leading-none text-brass">{{ $doctor->years_of_experience }}</span>
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
         Credentials band
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
        <section class="border-b border-line bg-paper">
            <div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:grid-cols-2 sm:px-8 lg:grid-cols-4">
                @foreach ($stats as [$value, $label])
                    <x-ui.stat :value="$value" :label="$label" data-reveal="{{ 70 * $loop->index }}" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- =================================================================
         Services
         ================================================================= --}}
    @if ($services->isNotEmpty())
        <section class="bg-paper">
            <div class="mx-auto max-w-7xl px-5 py-24 sm:px-8 sm:py-28">
                <div class="flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                    <x-ui.section-heading
                        eyebrow="What I treat"
                        title="Care for every stage"
                        lead="From a first consultation through to long-term follow-up." />

                    <x-ui.button :href="route('services')" variant="outline" class="shrink-0" data-reveal="100">
                        All services
                    </x-ui.button>
                </div>

                <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($services as $service)
                        <x-ui.service-card :service="$service" :index="$loop->index"
                                           data-reveal="{{ 70 * ($loop->index % 3) }}" />
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

        <div class="mx-auto grid max-w-7xl gap-14 px-5 py-24 sm:px-8 sm:py-28 lg:grid-cols-2 lg:gap-20">
            <div class="flex flex-col gap-7">
                <x-ui.section-heading
                    on-dark
                    eyebrow="About"
                    :title="'A word from ' . $doctor->name" />

                @if ($doctor->philosophy)
                    <p class="max-w-lg text-lg leading-relaxed text-white/60" data-reveal>
                        {{ Str::limit($doctor->philosophy, 320) }}
                    </p>
                @elseif ($doctor->bio)
                    <p class="max-w-lg text-lg leading-relaxed text-white/60" data-reveal>
                        {{ Str::limit(strip_tags($doctor->bio), 320) }}
                    </p>
                @endif

                <x-ui.button :href="route('about')" variant="outline-light" class="w-fit" data-reveal="80">
                    Read more
                </x-ui.button>
            </div>

            @if ($doctor->qualifications)
                <ul class="flex flex-col divide-y divide-night-line border-y border-night-line" data-reveal="120">
                    @foreach (array_slice($doctor->qualifications, 0, 4) as $qualification)
                        <li class="flex items-baseline gap-6 py-5">
                            <span class="font-display text-2xl leading-none text-brass/60" aria-hidden="true">
                                0{{ $loop->iteration }}
                            </span>

                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="text-lg font-semibold text-white">{{ $qualification['title'] ?? '' }}</span>
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
         Health videos
         ================================================================= --}}
    @if ($videos->isNotEmpty())
        <section class="bg-paper">
            <div class="mx-auto max-w-7xl px-5 py-24 sm:px-8 sm:py-28">
                <div class="flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                    <x-ui.section-heading
                        eyebrow="Patient education"
                        title="Understand your condition"
                        lead="Short films explaining what is happening, in plain language." />

                    <x-ui.button :href="route('videos.index')" variant="outline" class="shrink-0" data-reveal="100">
                        The library
                    </x-ui.button>
                </div>

                <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($videos as $video)
                        <x-ui.video-card :video="$video" data-reveal="{{ 70 * ($loop->index % 3) }}" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- =================================================================
         Testimonials
         ================================================================= --}}
    @if ($testimonials->isNotEmpty())
        <section class="border-y border-line bg-paper-shade">
            <div class="mx-auto max-w-7xl px-5 py-24 sm:px-8 sm:py-28">
                <x-ui.section-heading
                    eyebrow="In their words"
                    title="What patients say"
                    class="mb-14" />

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
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
        <section class="bg-paper">
            <div class="mx-auto max-w-7xl px-5 py-24 sm:px-8 sm:py-28">
                <div class="flex flex-col justify-between gap-8 lg:flex-row lg:items-end">
                    <x-ui.section-heading eyebrow="Reading" title="From the practice" />

                    <x-ui.button :href="route('blog.index')" variant="outline" class="shrink-0" data-reveal="100">
                        All articles
                    </x-ui.button>
                </div>

                <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
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
