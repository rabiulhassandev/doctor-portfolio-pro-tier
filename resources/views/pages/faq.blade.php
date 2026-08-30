@php
    // Flattened for the schema block: Google wants one list of questions, not
    // the grouping the page happens to display them in.
    $allFaqs = $groups->flatten(1);
@endphp

<x-layouts.app
    title="Common questions"
    :description="'Answers to the questions patients ask most often about appointments, fees and visiting ' . ($doctor->chamber_name ?: $doctor->name) . '.'">

    {{--
        schema.org FAQPage.

        The highest-value piece of structured data on a site like this: it is
        what lets Google show these answers directly in the results, which
        catches people who never reach the site at all.
    --}}
    @if ($allFaqs->isNotEmpty())
        @push('schema')
            <script type="application/ld+json">
                {!! json_encode([
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $allFaqs->map(fn ($faq) => [
                        '@type' => 'Question',
                        'name' => $faq->question,
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            // Plain text, which is why the admin field is plain
                            // too — Google rejects answers full of markup.
                            'text' => $faq->answer,
                        ],
                    ])->values()->all(),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
            </script>
        @endpush
    @endif

    <x-ui.page-hero
        eyebrow="Before you visit"
        title="Common questions"
        lead="The things patients ask the chamber most often. If yours is not here, telephone and we will answer it." />

    <section class="mx-auto max-w-3xl px-5 py-14 sm:px-8 sm:py-20">
        @if ($allFaqs->isEmpty())
            <x-ui.empty-state
                title="No questions yet"
                description="Answers to common questions will appear here."
                icon="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
        @else
            <div class="flex flex-col gap-10">
                @foreach ($groups as $category => $faqs)
                    <div class="flex flex-col gap-4" data-reveal>
                        {{-- The heading is only worth showing once there is more
                             than one group to tell apart. --}}
                        @if ($groups->count() > 1)
                            <h2 class="text-xl text-ink">{{ $category }}</h2>
                        @endif

                        <div class="flex flex-col gap-3">
                            @foreach ($faqs as $faq)
                                {{-- <details> rather than an Alpine accordion:
                                     it opens without JavaScript, it is findable
                                     with the browser's own Find on Page, and
                                     screen readers already know what it is. --}}
                                <details class="group rounded-[4px] border border-line bg-surface shadow-card transition-colors open:border-line-strong">
                                    <summary class="flex cursor-pointer list-none items-start justify-between gap-4 p-5 focus-visible:outline-2 focus-visible:outline-offset-3 focus-visible:outline-brass">
                                        <h3 class="text-[1.0625rem] font-semibold leading-snug text-ink transition-colors group-hover:text-brass">
                                            {{ $faq->question }}
                                        </h3>

                                        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-[3px] bg-paper-shade text-muted transition-transform duration-300 group-open:rotate-45 group-open:bg-brass-soft group-open:text-ink"
                                              aria-hidden="true">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                        </span>
                                    </summary>

                                    <div class="px-5 pb-5 text-[0.9375rem] leading-relaxed text-muted">
                                        {!! nl2br(e($faq->answer)) !!}
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="border-t border-line bg-paper-shade">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-5 px-5 py-16 text-center sm:px-8">
            <h2 class="text-2xl text-ink sm:text-3xl">Still not sure?</h2>
            <p class="max-w-lg leading-relaxed text-muted">
                Telephone the chamber and someone will answer properly — it is usually quicker than searching.
            </p>

            <div class="flex flex-wrap items-center justify-center gap-3">
                @if ($doctor->telHref())
                    <x-ui.button :href="$doctor->telHref()" size="lg">{{ $doctor->phone }}</x-ui.button>
                @endif

                @if (config('site.features.booking'))
                    <x-ui.button :href="route('booking')" variant="secondary" size="lg">Book an appointment</x-ui.button>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
