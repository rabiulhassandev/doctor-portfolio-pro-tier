{{--
    What a list shows when it has nothing in it.

        <x-ui.empty-state
            title="No appointments yet"
            description="When you book one, it will appear here." >
            <x-ui.button :href="route('booking')">Book an appointment</x-ui.button>
        </x-ui.empty-state>

    Worth doing properly. An empty page with nothing on it reads as broken; the
    same page with a sentence and a way forward reads as deliberate, and is the
    difference between a patient booking and a patient leaving.
--}}

@props([
    'title' => 'Nothing here yet',
    'description' => null,
    'icon' => null,
])

<div {{ $attributes->class('flex flex-col items-center gap-3 rounded-2xl border border-dashed border-line-strong bg-paper-shade/60 px-6 py-14 text-center') }}>
    <div class="flex size-12 items-center justify-center rounded-full bg-surface text-muted shadow-card" aria-hidden="true">
        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="{{ $icon ?: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z' }}" />
        </svg>
    </div>

    <h3 class="text-lg font-semibold text-ink">{{ $title }}</h3>

    @if ($description)
        <p class="max-w-sm text-[0.9375rem] leading-relaxed text-muted">{{ $description }}</p>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-2">{{ $slot }}</div>
    @endif
</div>
