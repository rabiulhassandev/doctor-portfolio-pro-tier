{{--
    The fixed bar at the bottom of the screen on phones.

    Two actions, always reachable, never more than a thumb's reach away: call
    the chamber, or book. On a medical site those are the only two things most
    visitors came to do, and making them hunt for either in a menu costs
    bookings.

    Hidden above `lg`, where the navbar already carries both. The layout adds
    matching bottom padding to <body> so the footer is never covered.
--}}

@php
    $tel = $doctor->telHref();
    $bookingEnabled = config('site.features.booking');
@endphp

@if ($tel || $bookingEnabled)
    {{-- Dark, matching the navbar at the other end of the screen. On a phone
         these two bars are the frame the whole site sits in, and a pale one at
         the bottom against a dark one at the top looks like an accident. --}}
    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-night-line bg-night/95 backdrop-blur-md lg:hidden"
         {{-- Clears the home indicator on iPhones, where a button flush to the
              bottom edge is genuinely hard to press. --}}
         style="padding-bottom: env(safe-area-inset-bottom);">

        <div class="flex items-stretch gap-2 px-3 py-2.5">
            @if ($tel)
                <a href="{{ $tel }}"
                   class="flex flex-1 items-center justify-center gap-2 rounded-[3px] border border-white/20 px-4 py-3 text-xs font-semibold uppercase tracking-[0.12em] text-white transition-colors active:bg-white/10">
                    <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />
                    </svg>
                    Call
                </a>
            @endif

            @if ($bookingEnabled)
                <a href="{{ route('booking') }}"
                   class="flex flex-1 items-center justify-center gap-2 rounded-[3px] bg-brass px-4 py-3 text-xs font-semibold uppercase tracking-[0.12em] text-night shadow-card transition-colors active:bg-brass-bright">
                    <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    Book now
                </a>
            @endif
        </div>
    </div>
@endif
