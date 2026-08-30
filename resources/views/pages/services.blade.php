<x-layouts.app
    title="Services"
    :description="'Treatments and procedures offered by ' . $doctor->name . ', ' . $doctor->specialization . '.'">

    <x-ui.page-hero
        eyebrow="What I treat"
        title="Services"
        lead="Everything offered at the chamber, from a first consultation through to long-term follow-up." />

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20">
        @if ($services->isEmpty())
            <x-ui.empty-state
                title="Nothing listed yet"
                description="Services will appear here once they have been added." />
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <x-ui.card class="flex h-full flex-col gap-4" data-reveal="{{ 50 * ($loop->index % 3) }}">
                        @if ($service->icon)
                            <span class="flex size-12 items-center justify-center rounded-xl bg-brand-soft text-brand" aria-hidden="true">
                                <x-dynamic-component :component="$service->icon" class="size-6" />
                            </span>
                        @endif

                        <h2 class="text-xl text-ink">{{ $service->title }}</h2>

                        @if ($service->summary)
                            <p class="text-[0.9375rem] font-medium leading-relaxed text-ink/80">{{ $service->summary }}</p>
                        @endif

                        @if ($service->description)
                            <p class="text-[0.9375rem] leading-relaxed text-muted">{{ $service->description }}</p>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </section>

    @if (config('site.features.booking'))
        <section class="border-t border-line bg-paper-shade">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-5 px-5 py-16 text-center sm:px-8">
                <h2 class="text-2xl text-ink sm:text-3xl">Not sure which you need?</h2>
                <p class="max-w-lg leading-relaxed text-muted">
                    Book a consultation and {{ Str::before($doctor->name, ' ') }} will work it out with you.
                </p>
                <x-ui.button :href="route('booking')" size="lg">Book an appointment</x-ui.button>
            </div>
        </section>
    @endif
</x-layouts.app>
