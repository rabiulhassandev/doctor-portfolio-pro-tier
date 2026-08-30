{{--
    One appointment, as the patient sees it.

    The date block on the left is the anchor — a patient scanning a list is
    looking for "when", not for a status badge. Everything else arranges itself
    around that.

        <x-patient.appointment-card :appointment="$appointment" />
--}}

@props([
    'appointment',
    // The dashboard shows a compact version; the list page shows the full one.
    'compact' => false,
])

@php
    use App\Enums\AppointmentStatus;
    use App\Enums\PaymentStatus;

    $local = $appointment->startsAtLocal();
    $isPast = $appointment->isPast();

    $tone = match ($appointment->status) {
        AppointmentStatus::Confirmed => 'positive',
        AppointmentStatus::Pending => 'caution',
        AppointmentStatus::Completed => 'brand',
        AppointmentStatus::Cancelled => 'negative',
        AppointmentStatus::Rescheduled => 'neutral',
    };
@endphp

<x-ui.card :href="route('patient.appointments.show', $appointment)" padding="none"
           {{ $attributes->class(['group', 'opacity-75 hover:opacity-100' => $isPast]) }}>

    <div class="flex items-stretch">
        {{-- The date, set large. --}}
        <div @class([
            'flex w-20 shrink-0 flex-col items-center justify-center gap-0.5 border-r border-line px-3 py-5 text-center sm:w-24',
            'bg-brass-soft text-ink' => ! $isPast && $appointment->status->isActive(),
            'bg-paper-shade text-muted' => $isPast || ! $appointment->status->isActive(),
        ])>
            <span class="text-[0.6875rem] font-semibold uppercase tracking-[0.12em]">
                {{ $local->format('M') }}
            </span>
            <span class="font-display text-3xl leading-none">{{ $local->format('j') }}</span>
            <span class="text-[0.6875rem] font-medium">{{ $local->format('D') }}</span>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-2 px-4 py-4 sm:px-5">
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-lg font-semibold tabular-nums text-ink">
                    {{ $local->format('g:i A') }}
                </p>

                <x-ui.badge :tone="$tone" dot>{{ $appointment->status->getLabel() }}</x-ui.badge>

                @if ($appointment->payment_status === PaymentStatus::Paid)
                    <x-ui.badge tone="positive">Paid</x-ui.badge>
                @elseif ($appointment->fee_amount && $appointment->status->isActive())
                    <x-ui.badge tone="neutral">{{ $appointment->formattedFee() }} due</x-ui.badge>
                @endif
            </div>

            <p class="text-sm text-muted">
                {{ $local->format('l, j F Y') }} · Booking {{ $appointment->reference }}
            </p>

            @unless ($compact)
                @if ($appointment->notes)
                    <p class="line-clamp-2 text-sm leading-relaxed text-muted">
                        “{{ $appointment->notes }}”
                    </p>
                @endif

                @if ($appointment->status === AppointmentStatus::Cancelled && $appointment->cancellation_reason)
                    <p class="text-sm text-negative">{{ $appointment->cancellation_reason }}</p>
                @endif

                @if ($appointment->status === AppointmentStatus::Rescheduled && $appointment->rescheduled_to_id)
                    <p class="text-sm text-muted">This appointment was moved to a new time.</p>
                @endif
            @endunless
        </div>

        {{-- The chevron nudges on hover: the smallest hint that the whole card
             is a link, without animating the card itself. --}}
        <div class="flex shrink-0 items-center pr-4 text-muted transition-transform duration-300 group-hover:translate-x-0.5 group-hover:text-brass">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        </div>
    </div>
</x-ui.card>
