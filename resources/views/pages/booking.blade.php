<x-layouts.app
    title="Book an appointment"
    :description="'Book an appointment with ' . $doctor->name . '. Choose a time that suits you and confirm online in a minute.'">

    <x-ui.page-hero
        eyebrow="Appointments"
        title="Book an appointment"
        width="medium"
        :lead="'Choose from the times ' . $doctor->shortName() . ' actually has free. You will get an email as soon as it is confirmed.'" />

    <section class="paper-grain relative isolate bg-paper">
        <div class="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-16">
            @livewire('booking-wizard')
        </div>
    </section>

    {{-- Reassurance below the fold: three things patients worry about before
         committing to a time, answered where they will look for them. --}}
    <section class="paper-grain relative isolate border-t border-line bg-paper-shade">
        <div class="mx-auto grid max-w-6xl gap-8 px-5 py-14 sm:px-8 sm:grid-cols-3">
            @foreach ([
                ['Confirmation by email', 'You get an email the moment the chamber confirms your time, and another if anything changes.'],
                ['Change it if you need to', 'Cancel or move your appointment from your account, right up until the day before.'],
                ['Pay however suits you', 'Settle online in advance, or simply pay at the chamber when you arrive.'],
            ] as $index => [$heading, $body])
                <div class="flex flex-col gap-2 border-t border-line pt-5" data-reveal="{{ 80 * $index }}">
                    <span class="numeral-index text-xl" aria-hidden="true">0{{ $index + 1 }}</span>
                    <h2 class="text-lg font-semibold text-ink">{{ $heading }}</h2>
                    <p class="leading-relaxed text-muted">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-layouts.app>
