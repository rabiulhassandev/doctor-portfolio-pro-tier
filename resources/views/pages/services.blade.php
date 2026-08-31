<x-layouts.app
    title="Services"
    :description="'Treatments and procedures offered by ' . $doctor->name . ', ' . $doctor->specialization . '.'">

    <x-ui.page-hero
        eyebrow="What I treat"
        title="Services"
        lead="Everything offered at the chamber, from a first consultation through to long-term follow-up." />

    <section class="paper-grain relative isolate bg-paper">
        {{-- Same container as the banner above it. A narrower one here left the
             list indented relative to the heading, which reads as a mistake
             rather than as a measure. --}}
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20">
            @if ($services->isEmpty())
                <x-ui.empty-state
                    title="Nothing listed yet"
                    description="Services will appear here once they have been added." />
            @else
                {{-- One column, not a grid. This is the page a patient reads to
                     work out whether their problem is one this chamber deals
                     with, and a list they can run an eye down beats three
                     columns they have to scan in a Z. --}}
                <div class="flex flex-col border-t border-line">
                    @foreach ($services as $service)
                        <x-ui.service-row
                            :service="$service"
                            :index="$loop->index"
                            detailed
                            class="border-b border-line"
                            data-reveal="{{ 50 * ($loop->index % 4) }}" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if (config('site.features.booking'))
        <x-ui.cta-band
            title="Not sure which you need?"
            :lead="'Book a consultation and ' . $doctor->shortName() . ' will work it out with you.'">
            <x-ui.button :href="route('booking')" size="lg">Book an appointment</x-ui.button>
        </x-ui.cta-band>
    @endif
</x-layouts.app>
