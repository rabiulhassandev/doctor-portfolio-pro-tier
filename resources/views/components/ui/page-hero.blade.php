{{--
    The banner at the top of every page except the home page.

        <x-ui.page-hero
            eyebrow="Patient education"
            title="Health videos"
            lead="Short films about the conditions I treat." />

    Deliberately restrained: a small tinted band rather than a second hero.
    Interior pages exist to be read, and a full-height image at the top of every
    one of them pushes the actual content below the fold.
--}}

@props([
    'eyebrow' => null,
    'title' => '',
    'lead' => null,
])

<header class="surface-grain relative isolate overflow-hidden border-b border-line bg-paper-shade">
    {{-- A single soft bloom, off to one side. Enough to stop the band reading
         as a flat rectangle; not enough to compete with the heading. --}}
    <div class="pointer-events-none absolute inset-0 -z-10"
         style="background: radial-gradient(32rem 20rem at 78% -20%, color-mix(in oklab, var(--brand-accent) 12%, transparent), transparent 70%);"
         aria-hidden="true"></div>

    <div class="mx-auto max-w-6xl px-5 py-14 sm:px-8 sm:py-20">
        <div class="flex max-w-2xl flex-col gap-4" data-reveal>
            @if ($eyebrow)
                <p class="eyebrow">{{ $eyebrow }}</p>
            @endif

            <h1 class="text-4xl text-ink sm:text-5xl">{{ $title }}</h1>

            @if ($lead)
                <p class="max-w-[36rem] text-lg leading-relaxed text-muted">{{ $lead }}</p>
            @endif

            {{ $slot }}
        </div>
    </div>
</header>
