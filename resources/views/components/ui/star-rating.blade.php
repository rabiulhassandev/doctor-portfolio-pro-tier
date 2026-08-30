{{--
    A star rating.

    Brass, matching the site's only accent. The visible stars are decorative;
    the rating is announced once in words, so a screen reader says "4 out of 5"
    rather than reading five identical shapes.
--}}

@props(['rating' => 5])

@php $rating = max(1, min(5, (int) $rating)); @endphp

<div {{ $attributes->class('flex items-center gap-1') }}>
    <span class="sr-only">{{ $rating }} out of 5</span>

    @for ($star = 1; $star <= 5; $star++)
        <svg @class(['size-3.5', 'text-brass' => $star <= $rating, 'text-line-strong' => $star > $rating])
             viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 1.5l2.47 5.01 5.53.8-4 3.9.94 5.5L10 14.13 5.06 16.7l.94-5.5-4-3.9 5.53-.8L10 1.5z" />
        </svg>
    @endfor
</div>
