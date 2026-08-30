<x-layouts.app
    title="Health videos"
    :description="'Short videos from ' . $doctor->name . ' explaining common conditions, tests and treatments in plain language.'">

    <x-ui.page-hero
        eyebrow="Patient education"
        title="Health videos"
        :lead="'Short films about the conditions ' . Str::before($doctor->name, ' ') . ' treats — what they are, what the tests involve, and what to expect afterwards.'" />

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-16">
        @livewire('video-library')
    </section>

    {{-- A closing prompt: someone who has just watched a video about their
         symptoms is the most likely person on the site to want an appointment. --}}
    @if (config('site.features.booking'))
        <section class="border-t border-line bg-paper-shade">
            <div class="mx-auto flex max-w-3xl flex-col items-center gap-5 px-5 py-14 text-center sm:px-8">
                <h2 class="text-2xl text-ink sm:text-3xl">Still have a question?</h2>
                <p class="max-w-lg leading-relaxed text-muted">
                    These videos cover the general picture. For anything about your own health,
                    book a consultation and {{ Str::before($doctor->name, ' ') }} will go through it with you.
                </p>
                <x-ui.button :href="route('booking')" size="lg">Book an appointment</x-ui.button>
            </div>
        </section>
    @endif
</x-layouts.app>
