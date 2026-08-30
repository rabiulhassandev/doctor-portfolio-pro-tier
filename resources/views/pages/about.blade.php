@php
    use App\Support\Media;

    $photo = Media::url($doctor->photo);
@endphp

<x-layouts.app
    :title="'About ' . $doctor->name"
    :description="$doctor->short_bio ?: ('About ' . $doctor->name . ', ' . $doctor->specialization . '.')">

    <x-site.physician-schema />

    <x-ui.page-hero
        eyebrow="About"
        :title="$doctor->name"
        :lead="$doctor->specialization . ($doctor->chamber_name ? ' · ' . $doctor->chamber_name : '')" />

    {{-- Biography --}}
    <section class="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-20">
        <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">

            {{-- Portrait and facts --}}
            <div class="flex flex-col gap-6" data-reveal>
                @if ($photo)
                    <img src="{{ $photo }}"
                         alt="{{ $doctor->name }}, {{ $doctor->specialization }}"
                         class="aspect-[4/5] w-full rounded-[4px] object-cover shadow-lift">
                @endif

                <dl class="flex flex-col gap-4 rounded-[4px] border border-line bg-surface p-5 shadow-card">
                    @if ($doctor->years_of_experience)
                        <div class="flex items-baseline justify-between gap-4 border-b border-line pb-4">
                            <dt class="text-sm text-muted">Experience</dt>
                            <dd class="font-semibold text-ink">{{ $doctor->years_of_experience }} years</dd>
                        </div>
                    @endif

                    @if ($registration = $doctor->registration())
                        <div class="flex items-baseline justify-between gap-4 border-b border-line pb-4">
                            <dt class="text-sm text-muted">Registration</dt>
                            <dd class="text-right font-semibold text-ink">{{ $registration }}</dd>
                        </div>
                    @endif

                    @if ($doctor->hasFee())
                        <div class="flex items-baseline justify-between gap-4 border-b border-line pb-4">
                            <dt class="text-sm text-muted">Consultation</dt>
                            <dd class="font-semibold text-ink">
                                {{ config('booking.payment.currency', 'BDT') }}
                                {{ number_format((float) $doctor->consultation_fee, 0) }}
                            </dd>
                        </div>
                    @endif

                    @if ($doctor->phone)
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-sm text-muted">Chamber</dt>
                            <dd>
                                <a href="{{ $doctor->telHref() }}" class="link-underline font-semibold text-ink">
                                    {{ $doctor->phone }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if (config('site.features.booking'))
                    <x-ui.button :href="route('booking')" block>Book an appointment</x-ui.button>
                @endif
            </div>

            {{-- Words --}}
            <div class="flex flex-col gap-10">
                @if ($doctor->bio)
                    <div class="prose-article" data-reveal>
                        {!! nl2br(e($doctor->bio)) !!}
                    </div>
                @endif

                @if ($doctor->philosophy)
                    <div class="flex flex-col gap-3 rounded-[4px] border-l-2 border-brass bg-paper-shade p-6" data-reveal>
                        <p class="eyebrow">My approach</p>
                        <div class="font-display text-xl leading-snug text-ink">
                            {!! nl2br(e($doctor->philosophy)) !!}
                        </div>
                    </div>
                @endif

                @if ($doctor->qualifications)
                    <div class="flex flex-col gap-5" data-reveal>
                        <h2 class="text-2xl text-ink">Qualifications</h2>

                        <ol class="relative flex flex-col gap-6 border-l border-line pl-6">
                            @foreach ($doctor->qualifications as $qualification)
                                <li class="relative">
                                    {{-- The dot on the timeline. --}}
                                    <span class="absolute -left-[1.8rem] top-1.5 size-3 rounded-[3px] border-2 border-paper bg-brass" aria-hidden="true"></span>

                                    <p class="font-semibold text-ink">{{ $qualification['title'] ?? '' }}</p>
                                    <p class="text-[0.9375rem] text-muted">
                                        {{ collect([$qualification['institution'] ?? null, $qualification['year'] ?? null])->filter()->implode(' · ') }}
                                    </p>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Services --}}
    @if ($services->isNotEmpty())
        <section class="border-t border-line bg-paper-shade">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20">
                <x-ui.section-heading eyebrow="What I treat" title="Services" class="mb-10" />

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($services as $service)
                        <x-ui.service-card :service="$service" data-reveal="{{ 50 * ($loop->index % 4) }}" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.app>
