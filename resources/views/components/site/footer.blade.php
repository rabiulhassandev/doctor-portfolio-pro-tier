{{--
    The footer.

    Inverted onto `ink_deep` — the one large dark field on the site. It gives
    the page a definite bottom edge, which a site that simply stops in pale grey
    never quite has.
--}}

@php
    $social = $doctor->activeSocialLinks();
    $hours = $doctor->scheduleRows();
    $status = $doctor->openStatus();
    $year = now()->year;
@endphp

<footer class="surface-grain relative isolate overflow-hidden bg-ink-deep text-white/70">
    {{-- A single bloom in the corner, at low opacity. Enough to stop a large
         dark rectangle reading as a printed block. --}}
    <div class="pointer-events-none absolute inset-0 -z-10"
         style="background: radial-gradient(40rem 24rem at 12% 0%, color-mix(in oklab, var(--brand-accent) 14%, transparent), transparent 70%);"
         aria-hidden="true"></div>

    <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-16">
        <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">

            {{-- Identity --}}
            <div class="flex flex-col gap-4 lg:col-span-1">
                <div class="flex flex-col gap-1">
                    <p class="font-display text-xl text-white">{{ $doctor->name }}</p>
                    <p class="text-sm text-white/60">{{ $doctor->specialization }}</p>

                    @if ($doctor->chamber_name)
                        <p class="text-sm text-white/60">{{ $doctor->chamber_name }}</p>
                    @endif
                </div>

                @if ($doctor->short_bio)
                    <p class="max-w-xs text-sm leading-relaxed text-white/55">
                        {{ Str::limit($doctor->short_bio, 130) }}
                    </p>
                @endif

                {{-- The registration number: a patient here checks this the way
                     a British patient checks a GMC number. --}}
                @if ($registration = $doctor->registration())
                    <p class="text-xs font-medium uppercase tracking-[0.1em] text-white/45">
                        {{ $registration }}
                    </p>
                @endif

                @if ($social->isNotEmpty())
                    <ul class="mt-1 flex items-center gap-2">
                        @foreach ($social as $network => $url)
                            <li>
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                   class="flex size-9 items-center justify-center rounded-full border border-white/12 text-white/70 transition-colors hover:border-white/30 hover:bg-white/5 hover:text-white"
                                   aria-label="{{ Str::headline($network) }}">
                                    <x-site.social-icon :network="$network" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Navigation --}}
            <nav class="flex flex-col gap-3" aria-label="Footer">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/45">Explore</p>

                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ([
                        ['About', 'about', null],
                        ['Services', 'services', null],
                        ['Health videos', 'videos.index', 'health_videos'],
                        ['Articles', 'blog.index', 'blog'],
                        ['Gallery', 'gallery', 'gallery'],
                        ['Common questions', 'faq', 'faq'],
                        ['Book an appointment', 'booking', 'booking'],
                        ['Contact', 'contact', null],
                    ] as [$label, $routeName, $feature])
                        @if (! $feature || config('site.features.'.$feature))
                            <li>
                                <a href="{{ route($routeName) }}" class="link-underline text-white/70 transition-colors hover:text-white">
                                    {{ $label }}
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </nav>

            {{-- Contact --}}
            <div class="flex flex-col gap-3">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/45">Find us</p>

                <address class="flex flex-col gap-2.5 text-sm not-italic">
                    @if ($address = $doctor->fullAddress())
                        <span class="text-white/70">{{ $address }}</span>
                    @endif

                    @if ($doctor->telHref())
                        <a href="{{ $doctor->telHref() }}" class="link-underline w-fit font-medium text-white transition-colors hover:text-accent">
                            {{ $doctor->phone }}
                        </a>
                    @endif

                    @if ($doctor->email)
                        <a href="mailto:{{ $doctor->email }}" class="link-underline w-fit break-all text-white/70 transition-colors hover:text-white">
                            {{ $doctor->email }}
                        </a>
                    @endif
                </address>
            </div>

            {{-- Hours --}}
            <div class="flex flex-col gap-3">
                <div class="flex items-center gap-2.5">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-white/45">Chamber hours</p>

                    @if ($status)
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-white/12 px-2 py-0.5 text-[0.6875rem] font-semibold {{ $status['is_open'] ? 'text-positive' : 'text-white/60' }}">
                            <span class="size-1.5 rounded-full {{ $status['is_open'] ? 'bg-positive' : 'bg-white/40' }}" aria-hidden="true"></span>
                            {{ $status['is_open'] ? 'Open' : 'Closed' }}
                        </span>
                    @endif
                </div>

                <dl class="flex flex-col gap-1.5 text-sm tabular-nums">
                    @foreach ($hours as $row)
                        <div @class([
                            'flex items-baseline justify-between gap-4',
                            'text-white' => $row['is_today'],
                            'text-white/60' => ! $row['is_today'],
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
        <div class="mt-12 flex flex-col items-start justify-between gap-3 border-t border-white/8 pt-6 text-xs text-white/45 sm:flex-row sm:items-center">
            <p>&copy; {{ $year }} {{ $doctor->name }}. All rights reserved.</p>

            <div class="flex items-center gap-4">
                @if (config('site.credit'))
                    <span>{!! config('site.credit') !!}</span>
                @endif

                <a href="{{ route('sitemap') }}" class="transition-colors hover:text-white/70">Sitemap</a>
            </div>
        </div>
    </div>
</footer>
