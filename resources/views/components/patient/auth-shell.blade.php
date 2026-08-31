{{--
    The frame around every patient auth screen — sign in, register, forgotten
    password, reset.

        <x-patient.auth-shell title="Create your account" subtitle="…">
            <form>…</form>
        </x-patient.auth-shell>

    ---------------------------------------------------------------------------
    A SPLIT SCREEN, NOT A CENTRED BOX
    ---------------------------------------------------------------------------

    Sign-up is the point where most people leave a medical site, usually because
    it suddenly looks like paperwork. A form floating in the middle of an empty
    page is the purest form of that: nothing on screen is doing anything except
    asking.

    So the screen is halved. The left is a photograph of the chamber under the
    dark overlay — the same treatment as every other page's banner, which is
    what stops the account area feeling like a different application — carrying
    the reason to have an account at all. The right is the form on a glass panel.

    Below `lg` the photograph becomes the background of the whole screen and the
    panel sits on top of it. The reassurance column is dropped rather than
    stacked above the form: on a phone it would push the fields below the fold,
    and the fields are what the visitor came for.
--}}

@props([
    'title',
    'subtitle' => null,
    // Shown above the card when a guest was interrupted mid-booking.
    'intendedSlot' => null,
])

@php
    use App\Support\Media;

    // Keyed rather than route-derived: four different routes render this shell
    // and they should all wear the same picture.
    $photo = Media::banner(key: 'patient.auth');
@endphp

<div class="surface-grain relative isolate flex min-h-screen flex-col overflow-hidden bg-night">

    {{-- The photograph. Full-bleed on small screens, half the width on large,
         where the form gets a surface of its own to sit on. --}}
    {{-- Three fifths, not a half. The picture has a hard right edge, and the
         scrim beside it has to be dark enough to hide that edge — at 50% the
         gradient was still translucent where the photograph stopped and the
         seam showed as a vertical line down the middle of the screen. --}}
    @if ($photo)
        <img src="{{ $photo }}" alt="" aria-hidden="true"
             class="absolute inset-0 -z-20 size-full object-cover opacity-75 [filter:saturate(0.75)_brightness(0.9)] lg:w-3/5">
    @endif

    <div class="hero-glow -z-10" aria-hidden="true"></div>

    {{-- The scrim. Heavier on small screens, where the form sits directly over
         the picture instead of beside it. --}}
    <div class="absolute inset-0 -z-10 bg-night/78 lg:bg-gradient-to-r lg:from-night/70 lg:via-night/55 lg:to-night"
         aria-hidden="true"></div>

    <div class="mx-auto grid w-full max-w-6xl flex-1 items-center gap-12 px-5 pb-14 pt-28 sm:px-8 sm:pb-20 sm:pt-36 lg:grid-cols-[minmax(0,1fr)_26rem] lg:gap-16">

        {{-- Reassurance column --}}
        <div class="hidden flex-col gap-8 lg:flex" data-reveal>
            <div class="flex flex-col gap-5">
                <div class="flex items-center gap-3">
                    <span class="rule-brass"></span>
                    <p class="eyebrow eyebrow-light">Patient account</p>
                </div>

                <h2 class="max-w-lg text-5xl leading-[1.05] text-white">
                    Your appointments and reports, in one place.
                </h2>

                <p class="max-w-md text-lg leading-relaxed text-white/65">
                    An account lets you book a time that suits you, see what is coming up, and collect
                    your prescriptions without telephoning the chamber.
                </p>
            </div>

            <ul class="flex flex-col divide-y divide-white/12 border-y border-white/12">
                @foreach ([
                    ['Book in seconds', 'Pick from the times actually free in the chamber — no waiting for a call back.'],
                    ['Everything in one place', 'Past visits, upcoming appointments and what you were prescribed.'],
                    ['Collect your documents', 'Prescriptions and reports, ready whenever you need them.'],
                ] as $index => [$heading, $body])
                    <li class="flex items-start gap-4 py-4" data-reveal="{{ 80 * ($index + 1) }}">
                        <span class="numeral-index mt-1 text-lg" aria-hidden="true">
                            0{{ $index + 1 }}
                        </span>
                        <div class="flex flex-col gap-0.5">
                            <p class="font-semibold text-white">{{ $heading }}</p>
                            <p class="text-[0.9375rem] leading-relaxed text-white/55">{{ $body }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- The form --}}
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

            {{-- Solid, not glass. The panel behind a password field has to be
                 opaque: `backdrop-filter` over a photograph moves as the page
                 scrolls, and reading your own typing through it is unpleasant
                 in a way that is hard to name and easy to feel. The brass rule
                 along the top edge is what ties it back to the site. --}}
            <div class="relative overflow-hidden border border-line bg-surface p-7 shadow-float sm:p-9">
                <span class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-brass/0 via-brass to-brass/0" aria-hidden="true"></span>

                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-2">
                        <h1 class="text-[1.75rem] leading-tight text-ink sm:text-3xl">{{ $title }}</h1>

                        @if ($subtitle)
                            <p class="text-[0.9375rem] leading-relaxed text-muted">{{ $subtitle }}</p>
                        @endif
                    </div>

                    {{ $slot }}
                </div>
            </div>

            @if (isset($footer))
                {{-- Sits on the dark, outside the panel. --}}
                <p class="text-center text-[0.9375rem] text-white/60">{{ $footer }}</p>
            @endif

            {{-- The way back to the site. Without it this screen is a dead end:
                 the navbar is above the fold on a phone but the form usually is
                 not, and somebody who has decided not to sign in should not have
                 to scroll up to leave. --}}
            <a href="{{ route('home') }}"
               class="link-underline mx-auto w-fit text-[0.8125rem] font-medium uppercase tracking-[0.13em] text-white/45 transition-colors hover:text-white">
                Back to the website
            </a>
        </div>
    </div>
</div>
