@php
    $hours = $doctor->scheduleRows();
    $status = $doctor->openStatus();

    /*
     | The map. A saved embed URL wins; otherwise one is built from the
     | coordinates. Google's plain /maps?q= embed needs no API key, which
     | matters for a template a buyer installs themselves.
     */
    $mapUrl = $doctor->map_embed_url
        ?: (($doctor->map_latitude && $doctor->map_longitude)
            ? 'https://maps.google.com/maps?q=' . $doctor->map_latitude . ',' . $doctor->map_longitude . '&z=16&output=embed'
            : null);
@endphp

<x-layouts.app
    title="Contact"
    :description="'Address, telephone number and opening hours for ' . ($doctor->chamber_name ?: $doctor->name) . '.'">

    <x-site.physician-schema />

    <x-ui.page-hero
        eyebrow="Find us"
        title="Contact"
        lead="Where the chamber is, when it is open, and how to reach us." />

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20">
        <div class="grid gap-8 lg:grid-cols-[1fr_1.1fr] lg:gap-12">

            {{-- Details --}}
            <div class="flex flex-col gap-6" data-reveal>

                {{-- The three ways to make contact, as big tappable targets.
                     On a phone these are the whole page. --}}
                <div class="grid gap-3 sm:grid-cols-2">
                    @if ($doctor->telHref())
                        <a href="{{ $doctor->telHref() }}"
                           class="card-lift flex items-center gap-3 rounded-[4px] border border-line bg-surface p-5 shadow-card hover:border-brass hover:shadow-lift">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-[3px] bg-brass-soft text-ink" aria-hidden="true">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                                </svg>
                            </span>
                            <span class="flex min-w-0 flex-col">
                                <span class="text-xs font-semibold uppercase tracking-[0.1em] text-muted">Telephone</span>
                                <span class="truncate font-semibold text-ink">{{ $doctor->phone }}</span>
                            </span>
                        </a>
                    @endif

                    @if ($whatsapp = $doctor->whatsappHref())
                        <a href="{{ $whatsapp }}" target="_blank" rel="noopener noreferrer"
                           class="card-lift flex items-center gap-3 rounded-[4px] border border-line bg-surface p-5 shadow-card hover:border-brass hover:shadow-lift">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-[3px] bg-[#25D366]/12 text-[#128C7E]" aria-hidden="true">
                                <svg class="size-5" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.87 9.87 0 004.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0012.04 2zm5.43 12.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.39-1.47-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.19 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.42-.07-.13-.27-.2-.57-.35z"/>
                                </svg>
                            </span>
                            <span class="flex min-w-0 flex-col">
                                <span class="text-xs font-semibold uppercase tracking-[0.1em] text-muted">WhatsApp</span>
                                <span class="truncate font-semibold text-ink">Message us</span>
                            </span>
                        </a>
                    @endif

                    @if ($doctor->email)
                        <a href="mailto:{{ $doctor->email }}"
                           class="card-lift flex items-center gap-3 rounded-[4px] border border-line bg-surface p-5 shadow-card hover:border-brass hover:shadow-lift sm:col-span-2">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-[3px] bg-brass-soft text-brass" aria-hidden="true">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                            </span>
                            <span class="flex min-w-0 flex-col">
                                <span class="text-xs font-semibold uppercase tracking-[0.1em] text-muted">Email</span>
                                <span class="truncate font-semibold text-ink">{{ $doctor->email }}</span>
                            </span>
                        </a>
                    @endif
                </div>

                {{-- Address --}}
                @if ($address = $doctor->fullAddress())
                    <x-ui.card>
                        <div class="flex flex-col gap-2">
                            <h2 class="text-lg font-semibold text-ink">
                                {{ $doctor->chamber_name ?: 'The chamber' }}
                            </h2>
                            <address class="not-italic leading-relaxed text-muted">{{ $address }}</address>
                        </div>
                    </x-ui.card>
                @endif

                {{-- Hours --}}
                <x-ui.card>
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-ink">Opening hours</h2>

                            @if ($status)
                                <x-ui.badge :tone="$status['is_open'] ? 'positive' : 'neutral'" dot>
                                    {{ $status['label'] }}
                                </x-ui.badge>
                            @endif
                        </div>

                        @if ($status)
                            <p class="-mt-2 text-sm text-muted">{{ $status['detail'] }}</p>
                        @endif

                        <dl class="flex flex-col divide-y divide-line">
                            @foreach ($hours as $row)
                                <div @class([
                                    'flex items-baseline justify-between gap-4 py-2.5 tabular-nums',
                                    'font-semibold text-ink' => $row['is_today'],
                                    'text-muted' => ! $row['is_today'],
                                ])>
                                    <dt>
                                        {{ $row['label'] }}
                                        @if ($row['is_today'])
                                            <span class="ml-1 text-xs font-normal text-brass">Today</span>
                                        @endif
                                    </dt>
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
                </x-ui.card>

                @if (config('site.features.booking'))
                    <x-ui.button :href="route('booking')" size="lg" block>Book an appointment</x-ui.button>
                @endif
            </div>

            {{-- Map --}}
            <div class="flex flex-col gap-4" data-reveal="100">
                @if ($mapUrl)
                    <div class="overflow-hidden rounded-[4px] border border-line shadow-card">
                        <iframe src="{{ $mapUrl }}"
                                title="Map showing the location of the chamber"
                                class="aspect-[4/3] w-full lg:aspect-auto lg:h-full lg:min-h-[32rem]"
                                style="border:0"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen></iframe>
                    </div>

                    @if ($doctor->map_latitude && $doctor->map_longitude)
                        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $doctor->map_latitude }},{{ $doctor->map_longitude }}"
                           target="_blank" rel="noopener noreferrer"
                           class="link-underline w-fit font-semibold text-ink">
                            Get directions
                        </a>
                    @endif
                @else
                    <div class="flex aspect-[4/3] items-center justify-center rounded-[4px] border border-dashed border-line-strong bg-paper-shade text-center text-muted lg:h-full">
                        <p class="max-w-xs px-6 leading-relaxed">
                            A map will appear here once the chamber's location has been set in the admin panel.
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
