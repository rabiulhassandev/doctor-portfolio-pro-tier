@php
    use App\Support\Media;
    use App\Support\Text;

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
    <section class="paper-grain relative isolate bg-paper">
    <div class="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-20">
        <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16">

            {{-- Portrait and facts --}}
            <div class="flex flex-col gap-8" data-reveal>
                @if ($photo)
                    {{-- The same offset brass frame as the home hero. Repeating
                         one device on the two pages that carry a photograph of
                         the doctor is what makes it read as a house style
                         rather than as decoration. --}}
                    <div class="relative">
                        <div class="absolute -bottom-3 -right-3 hidden size-full border border-brass/45 sm:block" aria-hidden="true"></div>

                        <img src="{{ $photo }}"
                             alt="{{ $doctor->name }}, {{ $doctor->specialization }}"
                             class="relative aspect-[4/5] w-full object-cover shadow-lift">
                    </div>
                @endif

                {{-- The facts, as a set of hairline rows. This was a bordered
                     card with a shadow, which made four short lines of text
                     look like a form. --}}
                <dl class="flex flex-col border-t border-line">
                    @if ($doctor->years_of_experience)
                        <div class="flex items-baseline justify-between gap-4 border-b border-line py-3.5">
                            <dt class="eyebrow">Experience</dt>
                            <dd class="font-semibold text-ink">{{ $doctor->years_of_experience }} years</dd>
                        </div>
                    @endif

                    @if ($registration = $doctor->registration())
                        <div class="flex items-baseline justify-between gap-4 border-b border-line py-3.5">
                            <dt class="eyebrow">Registration</dt>
                            <dd class="text-right font-semibold text-ink">{{ $registration }}</dd>
                        </div>
                    @endif

                    @if ($doctor->hasFee())
                        <div class="flex items-baseline justify-between gap-4 border-b border-line py-3.5">
                            <dt class="eyebrow">Consultation</dt>
                            <dd class="font-semibold text-ink">
                                {{ config('booking.payment.currency', 'BDT') }}
                                {{ number_format((float) $doctor->consultation_fee, 0) }}
                            </dd>
                        </div>
                    @endif

                    @if ($doctor->phone)
                        <div class="flex items-baseline justify-between gap-4 border-b border-line py-3.5">
                            <dt class="eyebrow">Chamber</dt>
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
                        {!! Text::rich($doctor->bio) !!}
                    </div>
                @endif

                @if ($doctor->philosophy)
                    {{-- No panel. A brass rule down the leading edge, the same
                         mark the testimonials use, and the words set large in
                         the display serif — this is a pulled quote, not a
                         callout box. --}}
                    <div class="flex flex-col gap-3 border-l border-brass pl-6 sm:pl-8" data-reveal>
                        <p class="eyebrow">My approach</p>
                        {{-- `flex flex-col gap-4` because Text::rich returns
                             real <p> elements and this block is not inside
                             `prose-article`, so nothing else would space
                             them. --}}
                        <div class="flex flex-col gap-4 font-display text-[1.5rem] leading-[1.3] text-ink sm:text-[1.75rem]">
                            {!! Text::rich($doctor->philosophy) !!}
                        </div>
                    </div>
                @endif

                @if ($doctor->qualifications)
                    <div class="flex flex-col gap-5" data-reveal>
                        <div class="flex items-center gap-3">
                            <span class="rule-brass" aria-hidden="true"></span>
                            <h2 class="eyebrow">Qualifications</h2>
                        </div>

                        {{-- Numbered rows rather than a dotted timeline. These
                             are degrees, not events: the year is one field of
                             several, and drawing a line down them implies a
                             chronology the list does not actually run in. --}}
                        <ol class="flex flex-col border-t border-line">
                            @foreach ($doctor->qualifications as $qualification)
                                <li class="flex items-baseline gap-5 border-b border-line py-4">
                                    <span class="numeral-index shrink-0 text-xl" aria-hidden="true">
                                        {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                    </span>

                                    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span class="font-semibold text-ink">{{ $qualification['title'] ?? '' }}</span>
                                        <span class="text-[0.9375rem] text-muted">
                                            {{ collect([$qualification['institution'] ?? null, $qualification['year'] ?? null])->filter()->implode(' · ') }}
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        </div>
    </div>
    </section>

    {{-- Services --}}
    @if ($services->isNotEmpty())
        <section class="paper-grain relative isolate border-t border-line bg-paper-shade">
            <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20">
                <x-ui.section-heading eyebrow="What I treat" title="Services" class="mb-8 sm:mb-12" />

                <div class="grid border-t border-line lg:grid-cols-2 lg:gap-x-12">
                    @foreach ($services as $service)
                        <x-ui.service-row
                            :service="$service"
                            :index="$loop->index"
                            class="border-b border-line"
                            data-reveal="{{ 50 * ($loop->index % 4) }}" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</x-layouts.app>
