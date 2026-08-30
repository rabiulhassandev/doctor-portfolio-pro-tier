{{--
    A message to the visitor: something worked, something failed, something
    needs their attention.

    Always announced to screen readers — role="alert" for problems (which
    interrupts), role="status" for confirmations (which waits politely).
    A left rule rather than a filled box: quieter, and it matches the way
    headings are marked elsewhere on the site.
--}}

@props([
    // positive | caution | negative | info
    'tone' => 'info',
    'title' => null,
])

@php
    $tones = [
        'positive' => [
            'box' => 'border-l-positive bg-positive-soft/60 text-positive',
            'icon' => 'M4.5 12.75l6 6 9-13.5',
        ],
        'caution' => [
            'box' => 'border-l-caution bg-caution-soft/60 text-caution',
            'icon' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
        ],
        'negative' => [
            'box' => 'border-l-negative bg-negative-soft/60 text-negative',
            'icon' => 'M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z',
        ],
        'info' => [
            'box' => 'border-l-brass bg-brass-soft/60 text-ink',
            'icon' => 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
        ],
    ];

    $style = $tones[$tone] ?? $tones['info'];
    $isProblem = in_array($tone, ['negative', 'caution'], true);
@endphp

<div role="{{ $isProblem ? 'alert' : 'status' }}"
     {{ $attributes->class(['flex items-start gap-3 border-l-2 px-4 py-3.5', $style['box']]) }}>

    <svg class="mt-0.5 size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
         stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $style['icon'] }}" />
    </svg>

    <div class="min-w-0 flex-1 text-[0.9375rem] leading-relaxed">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif

        @if (trim($slot) !== '')
            <div class="{{ $title ? 'mt-0.5 opacity-90' : '' }}">{{ $slot }}</div>
        @endif
    </div>
</div>
