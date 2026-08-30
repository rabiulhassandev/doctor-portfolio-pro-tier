{{--
    One service.

    The icon sits in a tinted square that shifts to the accent on hover — a
    single small colour move, rather than the whole card changing state. Cards
    that transform wholesale on hover are the thing that makes a grid feel
    twitchy.
--}}

@props(['service'])

<x-ui.card {{ $attributes->class('group flex h-full flex-col gap-4') }} :interactive="true">
    @if ($service->icon)
        <span class="flex size-12 items-center justify-center rounded-xl bg-brand-soft text-brand transition-colors duration-300 group-hover:bg-accent-soft group-hover:text-accent"
              aria-hidden="true">
            <x-dynamic-component :component="$service->icon" class="size-6" />
        </span>
    @endif

    <div class="flex flex-1 flex-col gap-2">
        <h3 class="text-lg text-ink">{{ $service->title }}</h3>

        @if ($summary = $service->shortSummary())
            <p class="text-[0.9375rem] leading-relaxed text-muted">{{ $summary }}</p>
        @endif
    </div>
</x-ui.card>
