{{--
    The quiet closing band at the foot of an interior page.

        <x-ui.cta-band title="Still not sure?" lead="…">
            <x-ui.button :href="route('booking')" size="lg">Book</x-ui.button>
        </x-ui.cta-band>

    Four pages were each hand-rolling this same centred block with slightly
    different padding, heading size and gap — which is exactly the kind of drift
    that makes a site feel assembled rather than designed.

    It sits on `paper_shade` with a brass rule above the heading, and it is the
    ONE centred thing on the site. Everything else is left-aligned on purpose
    (see the note in section-heading); a closing ask is the case where centring
    is right, because there is nothing else on the line to align to.

    Deliberately understated. The footer immediately below carries the real
    call to action in brass on near-black, and two loud asks in a row read as
    pushy on a page about somebody's heart.
--}}

@props([
    'eyebrow' => null,
    'title',
    'lead' => null,
])

<section class="paper-grain relative isolate border-t border-line bg-paper-shade">
    <div class="mx-auto flex max-w-3xl flex-col items-center gap-5 px-5 py-14 text-center sm:px-8 sm:py-20" data-reveal>
        @if ($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @else
            <span class="rule-brass" aria-hidden="true"></span>
        @endif

        <h2 class="text-[1.75rem] text-ink sm:text-4xl">{{ $title }}</h2>

        @if ($lead)
            <p class="max-w-lg leading-relaxed text-muted">{{ $lead }}</p>
        @endif

        {{-- Buttons go full width on a phone, where a centred pill floating in
             the middle of the screen is both hard to hit and hard to see. --}}
        <div class="flex w-full flex-col items-center gap-3 [&>*]:w-full sm:w-auto sm:flex-row sm:justify-center sm:[&>*]:w-auto">
            {{ $slot }}
        </div>
    </div>
</section>
