<x-layouts.app title="My account">
    <x-patient.shell
        :title="'Hello, ' . Str::before($patient->name, ' ')"
        subtitle="Here is what is coming up.">

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">

            {{-- Main column --}}
            <div class="flex flex-col gap-8">

                {{-- Upcoming --}}
                <section class="flex flex-col gap-4">
                    <div class="flex items-baseline justify-between gap-4">
                        <h2 class="text-xl text-ink">Your next appointments</h2>

                        @if ($upcoming->isNotEmpty())
                            <a href="{{ route('patient.appointments.index') }}"
                               class="link-underline text-sm font-medium text-ink">See all</a>
                        @endif
                    </div>

                    @forelse ($upcoming as $appointment)
                        <x-patient.appointment-card :appointment="$appointment" compact />
                    @empty
                        <x-ui.empty-state
                            title="Nothing booked yet"
                            description="Choose a time that suits you and the chamber will confirm it.">
                            @if (config('site.features.booking'))
                                <x-ui.button :href="route('booking')">Book an appointment</x-ui.button>
                            @endif
                        </x-ui.empty-state>
                    @endforelse
                </section>

                {{-- Recent visits --}}
                @if ($recent->isNotEmpty())
                    <section class="flex flex-col gap-4">
                        <h2 class="text-xl text-ink">Recent visits</h2>

                        @foreach ($recent as $appointment)
                            <x-patient.appointment-card :appointment="$appointment" compact />
                        @endforeach
                    </section>
                @endif
            </div>

            {{-- Side column --}}
            <aside class="flex flex-col gap-6">

                {{-- Documents --}}
                <x-ui.card>
                    <div class="flex flex-col gap-4">
                        <div class="flex items-baseline justify-between gap-3">
                            <h2 class="text-lg font-semibold text-ink">Your documents</h2>

                            @if ($documentCount > 4)
                                <a href="{{ route('patient.documents.index') }}"
                                   class="link-underline text-sm font-medium text-ink">All {{ $documentCount }}</a>
                            @endif
                        </div>

                        @forelse ($documents as $document)
                            <a href="{{ route('documents.download', $document) }}"
                               class="group flex items-start gap-3 rounded-[3px] border border-line p-3 transition-colors hover:border-line-strong hover:bg-paper-shade">
                                <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-[3px] bg-brass-soft text-ink" aria-hidden="true">
                                    <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                </span>

                                <span class="flex min-w-0 flex-1 flex-col">
                                    <span class="truncate text-[0.9375rem] font-medium text-ink group-hover:text-brass">
                                        {{ $document->title }}
                                    </span>
                                    <span class="text-xs text-muted">
                                        {{ $document->kind->getLabel() }} · {{ $document->created_at->format('j M Y') }}
                                    </span>
                                </span>

                                <svg class="mt-1 size-4 shrink-0 text-muted transition-transform group-hover:translate-y-0.5"
                                     fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                            </a>
                        @empty
                            <p class="text-[0.9375rem] leading-relaxed text-muted">
                                Prescriptions and reports appear here once the doctor has issued them.
                            </p>
                        @endforelse
                    </div>
                </x-ui.card>

                {{-- Chamber details --}}
                <x-ui.card>
                    <div class="flex flex-col gap-3">
                        <h2 class="text-lg font-semibold text-ink">The chamber</h2>

                        @if ($address = $doctor->fullAddress())
                            <p class="text-[0.9375rem] leading-relaxed text-muted">{{ $address }}</p>
                        @endif

                        @if ($doctor->telHref())
                            <a href="{{ $doctor->telHref() }}"
                               class="link-underline w-fit font-semibold text-ink">{{ $doctor->phone }}</a>
                        @endif

                        @if (config('site.features.booking'))
                            <x-ui.button :href="route('booking')" variant="outline" size="sm" class="mt-1 w-fit">
                                Book another appointment
                            </x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            </aside>
        </div>
    </x-patient.shell>
</x-layouts.app>
