{{--
    The footer.

    The site's darkest surface, and the place the brass is allowed to appear
    more than once — a footer is a directory, and the accent doing the work of
    separating four columns is the accent earning its keep.
--}}

@php
    $social = $doctor->activeSocialLinks();
    $hours = $doctor->scheduleRows();
    $status = $doctor->openStatus();
@endphp

<footer class="surface-grain relative isolate overflow-hidden bg-night text-white/60">
    {{-- A single bloom in the far corner, low opacity. Enough to stop a large
         dark field reading as a printed block. --}}
    <div class="pointer-events-none absolute inset-0 -z-10"
         style="background: radial-gradient(44rem 26rem at 8% 0%, color-mix(in oklab, var(--brand-brass) 11%, transparent), transparent 68%);"
         aria-hidden="true"></div>

    {{-- Call to action --}}
    @if (config('site.features.booking'))
        <div class="border-b border-night-line">
            <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 px-5 py-14 sm:px-8 lg:flex-row lg:items-center">
                <div class="flex flex-col gap-3" data-reveal>
                    <div class="flex items-center gap-3">
                        <span class="rule-brass"></span>
                        <p class="eyebrow eyebrow-light">Appointments</p>
                    </div>
                    <p class="max-w-xl font-display text-3xl leading-tight text-white sm:text-4xl">
                        Choose a time that suits you.
                    </p>
                </div>

                <x-ui.button :href="route('booking')" size="lg" data-reveal="100">
                    Book a consultation
                </x-ui.button>
            </div>
        </div>
    @endif

    <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-16">
        <div class="grid gap-12 sm:grid-cols-2 lg:grid-cols-4">

            {{-- Identity --}}
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-1.5">
                    <p class="font-display text-2xl text-white">{{ $doctor->name }}</p>
                    <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.2em] text-brass">
                        {{ $doctor->specialization }}
                    </p>

                    @if ($doctor->chamber_name)
                        <p class="text-sm text-white/50">{{ $doctor->chamber_name }}</p>
                    @endif
                </div>

                @if ($doctor->short_bio)
                    <p class="max-w-xs text-sm leading-relaxed text-white/45">
                        {{ Str::limit($doctor->short_bio, 120) }}
                    </p>
                @endif

                {{-- A patient here checks the registration number the way a
                     British patient checks a GMC number. --}}
                @if ($registration = $doctor->registration())
                    <p class="text-[0.6875rem] font-medium uppercase tracking-[0.14em] text-white/35">
                        {{ $registration }}
                    </p>
                @endif

                @if ($social->isNotEmpty())
                    <ul class="flex items-center gap-2">
                        @foreach ($social as $network => $url)
                            <li>
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                   class="flex size-9 items-center justify-center border border-white/12 text-white/60 transition-colors hover:border-brass hover:text-brass"
                                   aria-label="{{ Str::headline($network) }}">
                                    <x-site.social-icon :network="$network" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Navigation --}}
            <nav class="flex flex-col gap-4" aria-label="Footer">
                <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.18em] text-white/35">Explore</p>

                <ul class="flex flex-col gap-2.5 text-sm">
                    @foreach ([
                        ['About', 'about', null],
                        ['Services', 'services', null],
                        ['Health videos', 'videos.index', 'health_videos'],
                        ['Journal', 'blog.index', 'blog'],
                        ['Gallery', 'gallery', 'gallery'],
                        ['Common questions', 'faq', 'faq'],
                        ['Contact', 'contact', null],
                    ] as [$label, $routeName, $feature])
                        @if (! $feature || config('site.features.'.$feature))
                            <li>
                                <a href="{{ route($routeName) }}"
                                   class="link-underline text-white/60 transition-colors hover:text-white">{{ $label }}</a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </nav>

            {{-- Contact --}}
            <div class="flex flex-col gap-4">
                <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.18em] text-white/35">Find us</p>

                <address class="flex flex-col gap-3 text-sm not-italic">
                    @if ($address = $doctor->fullAddress())
                        <span class="leading-relaxed text-white/60">{{ $address }}</span>
                    @endif

                    @if ($doctor->telHref())
                        <a href="{{ $doctor->telHref() }}"
                           class="link-underline w-fit text-base font-semibold text-brass transition-colors hover:text-brass-bright">
                            {{ $doctor->phone }}
                        </a>
                    @endif

                    @if ($doctor->email)
                        <a href="mailto:{{ $doctor->email }}"
                           class="link-underline w-fit break-all text-white/60 transition-colors hover:text-white">
                            {{ $doctor->email }}
                        </a>
                    @endif
                </address>
            </div>

            {{-- Hours --}}
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.18em] text-white/35">Chamber hours</p>

                    @if ($status)
                        <span class="inline-flex items-center gap-1.5 border border-white/12 px-2 py-0.5 text-[0.625rem] font-semibold uppercase tracking-[0.1em] {{ $status['is_open'] ? 'text-positive' : 'text-white/45' }}">
                            <span class="size-1.5 rounded-full {{ $status['is_open'] ? 'bg-positive' : 'bg-white/35' }}" aria-hidden="true"></span>
                            {{ $status['is_open'] ? 'Open' : 'Closed' }}
                        </span>
                    @endif
                </div>

                <dl class="flex flex-col gap-2 text-sm tabular-nums">
                    @foreach ($hours as $row)
                        <div @class([
                            'flex items-baseline justify-between gap-4',
                            'text-white' => $row['is_today'],
                            'text-white/50' => ! $row['is_today'],
                        ])>
                            <dt class="{{ $row['is_today'] ? 'font-semibold' : '' }}">{{ $row['label'] }}</dt>
                            <dd>
                                @if ($row['is_closed'] || ! $row['opens'])
                                    Closed
                                @else
                                    {{ \Illuminate\Support\Carbon::parse($row['opens'])->format('g:i A') }}
                                    –
                                    {{ \Illuminate\Support\Carbon::parse($row['closes'])->format('g:i A') }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- Small print --}}
        <div class="mt-14 flex flex-col items-start justify-between gap-3 border-t border-night-line pt-7 text-xs text-white/35 sm:flex-row sm:items-center">
            <p>&copy; {{ now()->year }} {{ $doctor->name }}. All rights reserved.</p>

            <div class="flex items-center gap-5">
                @if (config('site.credit'))
                    <span>{!! config('site.credit') !!}</span>
                @endif

                <a href="{{ route('sitemap') }}" class="transition-colors hover:text-white/60">Sitemap</a>
            </div>
        </div>
    </div>
</footer>
