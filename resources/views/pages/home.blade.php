@php
    use App\Support\Media;

    $photo = Media::url($doctor->photo);
    $status = $doctor->openStatus();
    $bookingEnabled = config('site.features.booking');
    $firstName = Str::of($doctor->name)->replaceMatches('/^Dr\.?\s*/i', '')->before(' ')->toString();
@endphp

<x-layouts.app :transparent-nav="true">

    <x-site.physician-schema />

    {{-- =================================================================
         Hero
         ================================================================= --}}
    <section class="relative isolate overflow-hidden bg-paper pt-28 sm:pt-32 lg:pt-36">
        {{-- The slow drifting wash. Far too slow to read as an animation; what
             it does is stop the largest flat area on the site from looking like
             a printed rectangle. --}}
        <div class="hero-wash -z-10" aria-hidden="true"></div>

        <div class="mx-auto grid max-w-7xl gap-12 px-5 pb-16 sm:px-8 sm:pb-20 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:gap-16 lg:pb-24">

            {{-- Copy --}}
            <div class="flex flex-col items-start gap-6" data-reveal>
                @if ($status)
                    <span class="inline-flex items-center gap-2 rounded-full border border-line bg-surface/80 px-3.5 py-1.5 text-sm backdrop-blur">
                        <span class="relative flex size-2">
                            @if ($status['is_open'])
                                <span class="absolute inline-flex size-full animate-ping rounded-full bg-positive opacity-60 motion-reduce:hidden"></span>
                            @endif
                            <span class="relative inline-flex size-2 rounded-full {{ $status['is_open'] ? 'bg-positive' : 'bg-muted' }}"></span>
                        </span>
                        <span class="font-semibold {{ $status['is_open'] ? 'text-positive' : 'text-ink' }}">{{ $status['label'] }}</span>
                        <span class="text-muted">{{ $status['detail'] }}</span>
                    </span>
                @endif

                <div class="flex flex-col gap-4">
                    <p class="eyebrow">{{ $doctor->specialization }}</p>

                    <h1 class="max-w-xl text-4xl text-ink sm:text-5xl lg:text-6xl">
                        {{ $doctor->tagline ?: 'Careful, unhurried care for your heart.' }}
                    </h1>

                    @if ($doctor->short_bio)
                        <p class="max-w-lg text-lg leading-relaxed text-muted">{{ $doctor->short_bio }}</p>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    @if ($bookingEnabled)
                        <x-ui.button :href="route('booking')" size="lg">Book an appointment</x-ui.button>
                    @endif

                    @if ($doctor->telHref())
                        <x-ui.button :href="$doctor->telHref()" variant="secondary" size="lg">
                            {{ $doctor->phone }}
                        </x-ui.button>
                    @endif
                </div>

                @if ($registration = $doctor->registration())
                    {{-- A patient here checks this the way a British patient
                         checks a GMC number. It belongs above the fold. --}}
                    <p class="flex items-center gap-2 text-sm text-muted">
                        <svg class="size-4 text-accent" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                        </svg>
                        {{ $registration }}
                    </p>
                @endif
            </div>

            {{-- Portrait --}}
            <div class="relative" data-reveal="120">
                @if ($photo)
                    <div class="relative overflow-hidden rounded-[2rem] shadow-float">
                        <img src="{{ $photo }}"
                             alt="{{ $doctor->name }}, {{ $doctor->specialization }}"
                             class="aspect-[4/5] w-full object-cover">

                        {{-- A card floating over the corner: the practice's
                             single most persuasive fact, wherever it is. --}}
                        @if ($doctor->years_of_experience)
                            <div class="absolute bottom-5 left-5 flex items-center gap-3 rounded-2xl border border-line bg-surface/95 px-4 py-3 shadow-lift backdrop-blur">
                                <span class="font-display text-3xl leading-none text-brand">{{ $doctor->years_of_experience }}</span>
                                <span class="text-sm leading-tight text-muted">years of<br>practice</span>
                            </div>
                        @endif
                    </div>
                @else
                    {{-- No photograph uploaded yet. A branded panel rather than
                         a grey box with a broken image icon. --}}
                    <div class="surface-grain relative flex aspect-[4/5] items-center justify-center overflow-hidden rounded-[2rem] shadow-float"
                         style="background: linear-gradient(150deg, var(--brand-primary), var(--brand-accent));">
                        <span class="font-display text-7xl text-white/90">
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
            $doctor->years_of_experience ? ['value' => $doctor->years_of_experience . '+', 'label' => 'Years in practice'] : null,
            $services->isNotEmpty() ? ['value' => $services->count() . '+', 'label' => 'Services offered'] : null,
            $doctor->qualifications ? ['value' => count($doctor->qualifications), 'label' => 'Qualifications'] : null,
            $testimonials->isNotEmpty() ? ['value' => $testimonials->count() . '+', 'label' => 'Patient reviews'] : null,
        ])->filter()->values();
    @endphp

    @if ($stats->isNotEmpty())
        <section class="border-y border-line bg-paper-shade">
            <div class="mx-auto grid max-w-5xl gap-8 px-5 py-10 sm:grid-cols-{{ min(4, $stats->count()) }} sm:px-8">
                @foreach ($stats as $stat)
                    <x-ui.stat :value="$stat['value']" :label="$stat['label']" data-reveal="{{ 60 * $loop->index }}" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- =================================================================
         Services
         ================================================================= --}}
    @if ($services->isNotEmpty())
        <section class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-24">
            <x-ui.section-heading
                eyebrow="What I treat"
                title="Care for every stage"
                lead="From a first consultation through to long-term follow-up."
                class="mb-12" />

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <x-ui.service-card :service="$service" data-reveal="{{ 60 * ($loop->index % 3) }}" />
                @endforeach
            </div>

            <div class="mt-10 flex justify-center" data-reveal>
                <x-ui.button :href="route('services')" variant="secondary">See everything I treat</x-ui.button>
            </div>
        </section>
    @endif

    {{-- =================================================================
         About
         ================================================================= --}}
    <section class="border-y border-line bg-paper-shade">
        <div class="mx-auto grid max-w-6xl gap-10 px-5 py-20 sm:px-8 lg:grid-cols-2 lg:items-center lg:gap-16">
            <div class="flex flex-col gap-5" data-reveal>
                <x-ui.section-heading
                    align="left"
                    eyebrow="About"
                    :title="'A word from ' . $doctor->name" />

                @if ($doctor->philosophy)
                    <p class="text-lg leading-relaxed text-muted">{{ Str::limit($doctor->philosophy, 320) }}</p>
                @elseif ($doctor->bio)
                    <p class="text-lg leading-relaxed text-muted">{{ Str::limit(strip_tags($doctor->bio), 320) }}</p>
                @endif

                <x-ui.button :href="route('about')" variant="secondary" class="w-fit">Read more about me</x-ui.button>
            </div>

            @if ($doctor->qualifications)
                <ul class="flex flex-col gap-3" data-reveal="100">
                    @foreach (array_slice($doctor->qualifications, 0, 4) as $index => $qualification)
                        <li class="flex items-start gap-4 rounded-xl border border-line bg-surface p-4 shadow-card">
                            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent-soft text-accent" aria-hidden="true">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.62 48.62 0 0112 20.904a48.62 48.62 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                                </svg>
                            </span>

                            <span class="flex min-w-0 flex-col">
                                <span class="font-semibold text-ink">{{ $qualification['title'] ?? '' }}</span>
                                <span class="text-sm text-muted">
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
        <section class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-24">
            <x-ui.section-heading
                eyebrow="Patient education"
                title="Understand your condition"
                lead="Short videos explaining what is happening, in plain language."
                class="mb-12" />

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($videos as $video)
                    <x-ui.video-card :video="$video" data-reveal="{{ 60 * ($loop->index % 3) }}" />
                @endforeach
            </div>

            <div class="mt-10 flex justify-center" data-reveal>
                <x-ui.button :href="route('videos.index')" variant="secondary">Browse the video library</x-ui.button>
            </div>
        </section>
    @endif

    {{-- =================================================================
         Testimonials
         ================================================================= --}}
    @if ($testimonials->isNotEmpty())
        <section class="border-y border-line bg-paper-shade">
            <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-24">
                <x-ui.section-heading
                    eyebrow="In their words"
                    title="What patients say"
                    class="mb-12" />

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($testimonials->take(3) as $testimonial)
                        <x-ui.testimonial-card :testimonial="$testimonial" data-reveal="{{ 60 * $loop->index }}" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- =================================================================
         Latest articles
         ================================================================= --}}
    @if ($posts->isNotEmpty())
        <section class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-24">
            <x-ui.section-heading
                eyebrow="Reading"
                title="From the practice"
                class="mb-12" />

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-ui.post-card :post="$post" data-reveal="{{ 60 * $loop->index }}" />
                @endforeach
            </div>

            <div class="mt-10 flex justify-center" data-reveal>
                <x-ui.button :href="route('blog.index')" variant="secondary">All articles</x-ui.button>
            </div>
        </section>
    @endif

    {{-- =================================================================
         Closing call to action
         ================================================================= --}}
    <section class="surface-grain relative isolate overflow-hidden bg-ink-deep">
        <div class="pointer-events-none absolute inset-0 -z-10"
             style="background:
                radial-gradient(40rem 24rem at 20% 0%, color-mix(in oklab, var(--brand-primary) 40%, transparent), transparent 65%),
                radial-gradient(34rem 22rem at 82% 100%, color-mix(in oklab, var(--brand-accent) 30%, transparent), transparent 65%);"
             aria-hidden="true"></div>

        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 px-5 py-20 text-center sm:px-8 sm:py-24" data-reveal>
            <h2 class="text-3xl text-white sm:text-4xl lg:text-5xl">
                Ready when you are
            </h2>

            <p class="max-w-lg text-lg leading-relaxed text-white/70">
                @if ($bookingEnabled)
                    Choose a time that suits you. You will have a confirmation by email within minutes.
                @else
                    Telephone the chamber and we will find you a time.
                @endif
            </p>

            <div class="flex flex-wrap items-center justify-center gap-3">
                @if ($bookingEnabled)
                    <x-ui.button :href="route('booking')" variant="white" size="lg">Book an appointment</x-ui.button>
                @endif

                @if ($doctor->telHref())
                    <a href="{{ $doctor->telHref() }}"
                       class="link-underline text-lg font-semibold text-white transition-colors hover:text-accent">
                        {{ $doctor->phone }}
                    </a>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
