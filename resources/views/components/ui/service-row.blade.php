{{--
    One service, as an editorial row.

        <x-ui.service-row :service="$service" :index="$loop->index" />

    ---------------------------------------------------------------------------
    WHY A ROW AND NOT A CARD
    ---------------------------------------------------------------------------

    The light sections used to be four card grids stacked on top of each other —
    services, videos, testimonials, articles — and a page of identical
    three-column grids is the single thing that most makes a site look like a
    template, however carefully each card is set.

    A hairline-separated list gives the services their own shape, reads as a
    contents page rather than a product catalogue, and behaves better on a phone
    than a card ever does: the row is already the full width of the screen, so
    it stacks with nothing to reflow and the whole thing is one tap target.

    Set `detailed` on the standalone /services page, where there is room for the
    longer description and no reason to hold it back.
--}}

@props([
    'service',
    'index' => null,
    // Adds the long description under the summary.
    'detailed' => false,
])

<div {{ $attributes->class('row-editorial group/row flex items-start gap-5 px-4 py-7 sm:gap-8 sm:px-6 sm:py-9') }}>

    @if ($index !== null)
        <span class="numeral-index shrink-0 pt-1 text-2xl sm:text-4xl" aria-hidden="true">
            {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
        </span>
    @endif

    {{-- Capped at a readable measure. On the home page's two-column grid the
         column is narrower than this anyway, so the cap only bites on the
         standalone /services page — where the rules run the full width of the
         container and the words must not. --}}
    <div class="flex min-w-0 max-w-3xl flex-1 flex-col gap-2">
        <h3 class="text-lg leading-snug text-ink transition-colors duration-400 group-hover/row:text-brass sm:text-xl">
            {{ $service->title }}
        </h3>

        @if ($summary = $service->shortSummary($detailed ? 200 : 140))
            <p class="text-[0.9375rem] leading-relaxed text-muted">{{ $summary }}</p>
        @endif

        @if ($detailed && filled($service->description))
            <p class="mt-1 text-[0.9375rem] leading-relaxed text-muted/85">{{ $service->description }}</p>
        @endif
    </div>

    {{-- The icon, where the doctor chose one. Small, quiet, and on the far side
         of the row so it aligns down the right edge of the list instead of
         competing with the numeral. Dropped below `sm`, where the row needs
         every pixel of its width for the words. --}}
    @if ($service->icon)
        {{-- `ml-auto` so it anchors the right edge of the row rather than
             trailing the text, which on the wide /services list left it
             stranded in the middle of the page. --}}
        <span class="ml-auto hidden shrink-0 pt-1 text-line-strong transition-colors duration-500 group-hover/row:text-brass sm:block"
              aria-hidden="true">
            <x-dynamic-component :component="$service->icon" class="size-6" />
        </span>
    @endif
</div>
