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

<div class="relative isolate overflow-hidden">
    {{-- Two very soft blooms. The auth screens have almost no content, and a
         flat expanse of paper behind one small card looks unfinished. --}}
    <div class="pointer-events-none absolute inset-0 -z-10"
         style="background:
            radial-gradient(36rem 24rem at 15% 0%, color-mix(in oklab, var(--brand-primary) 12%, transparent), transparent 65%),
            radial-gradient(32rem 22rem at 88% 100%, color-mix(in oklab, var(--brand-accent) 12%, transparent), transparent 65%);"
         aria-hidden="true"></div>

    <div class="mx-auto grid max-w-6xl gap-10 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[minmax(0,1fr)_26rem] lg:items-center lg:gap-16">

        {{-- Reassurance column. Hidden on phones, where it would push the form
             below the fold — the form is what people came for. --}}
        <div class="hidden flex-col gap-6 lg:flex" data-reveal>
            <div class="flex flex-col gap-3">
                <p class="eyebrow">Patient account</p>
                <h2 class="max-w-lg text-4xl text-ink">
                    Your appointments and reports, in one place.
                </h2>
                <p class="max-w-md text-lg leading-relaxed text-muted">
                    An account lets you book a time that suits you, see what is coming up, and collect
                    your prescriptions without telephoning the chamber.
                </p>
            </div>

            <ul class="flex flex-col gap-4">
                @foreach ([
                    ['Book in seconds', 'Pick from the times actually free in the chamber — no waiting for a call back.'],
                    ['Everything in one place', 'Past visits, upcoming appointments and what you were prescribed.'],
                    ['Collect your documents', 'Prescriptions and reports, ready to download whenever you need them.'],
                ] as $index => [$heading, $body])
                    <li class="flex items-start gap-3.5" data-reveal="{{ 80 * ($index + 1) }}">
                        <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent" aria-hidden="true">
                            <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </span>
                        <div class="flex flex-col gap-0.5">
                            <p class="font-semibold text-ink">{{ $heading }}</p>
                            <p class="text-[0.9375rem] leading-relaxed text-muted">{{ $body }}</p>
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
                <p class="text-center text-[0.9375rem] text-muted">{{ $footer }}</p>
            @endif
        </div>
    </div>
</div>
