{{--
    One service.

    A numbered list rather than an icon grid. The index in the display serif at
    the top-left does the work an icon used to: it gives the card an anchor and
    the grid a rhythm, without needing artwork that will look generic whatever
    you choose.
--}}

@props(['service', 'index' => null])

<x-ui.card {{ $attributes->class('flex h-full flex-col gap-5') }} :interactive="true">
    <div class="flex items-start justify-between gap-4">
        @if ($index !== null)
            <span class="font-display text-4xl leading-none text-brass/70" aria-hidden="true">
                {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
            </span>
        @endif

        @if ($service->icon)
            <span class="text-muted transition-colors duration-500 group-hover/card:text-brass" aria-hidden="true">
                <x-dynamic-component :component="$service->icon" class="size-6" />
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-2.5">
        <h3 class="text-lg text-ink">{{ $service->title }}</h3>

        @if ($summary = $service->shortSummary())
            <p class="text-[0.9375rem] leading-relaxed text-muted">{{ $summary }}</p>
        @endif
    </div>
</x-ui.card>
