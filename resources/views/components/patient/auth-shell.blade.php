{{--
    The frame around every patient auth screen.

    A single centred card on a tinted wash, with the reassurance panel beside it
    on wide screens. Sign-up is the point where most people leave a medical
    site — usually because it suddenly looks like paperwork — so this screen is
    deliberately short, warm, and says what the account is actually for.

        <x-patient.auth-shell title="Create your account" subtitle="…">
            <form>…</form>
        </x-patient.auth-shell>
--}}

@props([
    'title',
    'subtitle' => null,
    // Shown above the card when a guest was interrupted mid-booking.
    'intendedSlot' => null,
])

{{-- Full-height dark. Signing in is the one screen with nothing else on it,
     so it gets the site's most atmospheric treatment. --}}
<div class="surface-grain relative isolate min-h-screen overflow-hidden bg-night">
    <div class="hero-glow -z-10" aria-hidden="true"></div>

    <div class="mx-auto grid max-w-6xl gap-12 px-5 pb-16 pt-32 sm:px-8 sm:pb-24 sm:pt-40 lg:grid-cols-[minmax(0,1fr)_26rem] lg:items-center lg:gap-20">

        {{-- Reassurance column. Hidden on phones, where it would push the form
             below the fold — the form is what people came for. --}}
        <div class="hidden flex-col gap-8 lg:flex" data-reveal>
            <div class="flex flex-col gap-5">
                <div class="flex items-center gap-3">
                    <span class="rule-brass"></span>
                    <p class="eyebrow eyebrow-light">Patient account</p>
                </div>

                <h2 class="max-w-lg text-5xl leading-[1.05] text-white">
                    Your appointments and reports, in one place.
                </h2>

                <p class="max-w-md text-lg leading-relaxed text-white/60">
                    An account lets you book a time that suits you, see what is coming up, and collect
                    your prescriptions without telephoning the chamber.
                </p>
            </div>

            <ul class="flex flex-col divide-y divide-night-line border-y border-night-line">
                @foreach ([
                    ['Book in seconds', 'Pick from the times actually free in the chamber — no waiting for a call back.'],
                    ['Everything in one place', 'Past visits, upcoming appointments and what you were prescribed.'],
                    ['Collect your documents', 'Prescriptions and reports, ready whenever you need them.'],
                ] as $index => [$heading, $body])
                    <li class="flex items-start gap-4 py-4" data-reveal="{{ 80 * ($index + 1) }}">
                        <span class="mt-1 font-display text-lg leading-none text-brass/70" aria-hidden="true">
                            0{{ $index + 1 }}
                        </span>
                        <div class="flex flex-col gap-0.5">
                            <p class="font-semibold text-white">{{ $heading }}</p>
                            <p class="text-[0.9375rem] leading-relaxed text-white/50">{{ $body }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- The form itself --}}
        <div class="flex w-full flex-col gap-4" data-reveal>
            @if ($intendedSlot)
                {{-- A guest who chose a time and was asked to sign in first. Say
                     so plainly, or the interruption looks like the site losing
                     their booking. --}}
                <x-ui.alert tone="info" title="Your time is being held">
                    Sign in and we will finish booking
                    <strong>{{ \App\Support\Clock::parse($intendedSlot)->format('l j F, g:i A') }}</strong>.
                </x-ui.alert>
            @endif

            @if (session('status'))
                <x-ui.alert tone="positive">{{ session('status') }}</x-ui.alert>
            @endif

            <x-ui.card padding="loose">
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-1.5">
                        <h1 class="text-2xl text-ink sm:text-3xl">{{ $title }}</h1>

                        @if ($subtitle)
                            <p class="text-[0.9375rem] leading-relaxed text-muted">{{ $subtitle }}</p>
                        @endif
                    </div>

                    {{ $slot }}
                </div>
            </x-ui.card>

            @if (isset($footer))
                {{-- Sits on the dark, outside the card. --}}
                <p class="text-center text-[0.9375rem] text-white/55">{{ $footer }}</p>
            @endif
        </div>
    </div>
</div>
