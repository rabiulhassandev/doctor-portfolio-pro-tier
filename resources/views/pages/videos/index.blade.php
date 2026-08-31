<x-layouts.app
    title="Health videos"
    :description="'Short videos from ' . $doctor->name . ' explaining common conditions, tests and treatments in plain language.'">

    <x-ui.page-hero
        eyebrow="Patient education"
        title="Health videos"
        :lead="'Short films about the conditions ' . $doctor->shortName() . ' treats — what they are, what the tests involve, and what to expect afterwards.'" />

    <section class="paper-grain relative isolate bg-paper">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-16">
            @livewire('video-library')
        </div>
    </section>

    {{-- A closing prompt: someone who has just watched a video about their
         symptoms is the most likely person on the site to want an appointment. --}}
    @if (config('site.features.booking'))
        <x-ui.cta-band
            title="Still have a question?"
            :lead="'These videos cover the general picture. For anything about your own health, book a consultation and ' . $doctor->shortName() . ' will go through it with you.'">
            <x-ui.button :href="route('booking')" size="lg">Book an appointment</x-ui.button>
        </x-ui.cta-band>
    @endif
</x-layouts.app>
