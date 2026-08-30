@php
    use App\Enums\AppointmentStatus;
    use App\Enums\BookingActor;
    use App\Enums\PaymentStatus;

    $local = $appointment->startsAtLocal();
    $canCancel = $workflow->canCancel($appointment, BookingActor::Patient);
    $needsPayment = $appointment->status->isActive()
        && $appointment->isUnpaid()
        && $appointment->fee_amount
        && config('booking.payment.enabled');
@endphp

<x-layouts.app :title="'Appointment ' . $appointment->reference">
    <x-patient.shell
        title="Your appointment"
        :subtitle="'Booking ' . $appointment->reference">

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">

            {{-- Detail --}}
            <div class="flex flex-col gap-6">
                <x-ui.card padding="loose">
                    <div class="flex flex-col gap-6">

                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex flex-col gap-1">
                                <p class="eyebrow">{{ $local->format('l') }}</p>
                                <p class="font-display text-3xl text-ink sm:text-4xl">
                                    {{ $local->format('j F Y') }}
                                </p>
                                <p class="text-lg font-semibold tabular-nums text-brand">
                                    {{ $appointment->timeLabel() }}
                                </p>
                            </div>

                            <x-ui.badge dot :tone="match ($appointment->status) {
                                AppointmentStatus::Confirmed => 'positive',
                                AppointmentStatus::Pending => 'caution',
                                AppointmentStatus::Completed => 'brand',
                                AppointmentStatus::Cancelled => 'negative',
                                AppointmentStatus::Rescheduled => 'neutral',
                            }">
                                {{ $appointment->status->getLabel() }}
                            </x-ui.badge>
                        </div>

                        {{-- Status explanation, in plain words. A badge alone
                             leaves a patient wondering what they should do. --}}
                        @if ($appointment->status === AppointmentStatus::Pending)
                            <x-ui.alert tone="caution" title="Waiting for the chamber to confirm">
                                We have your request. The chamber will confirm shortly, and we will email you.
                            </x-ui.alert>
                        @elseif ($appointment->status === AppointmentStatus::Confirmed)
                            <x-ui.alert tone="positive" title="This appointment is confirmed">
                                Please arrive a few minutes early.
                            </x-ui.alert>
                        @elseif ($appointment->status === AppointmentStatus::Cancelled)
                            <x-ui.alert tone="negative" title="This appointment was cancelled">
                                {{ $appointment->cancellation_reason ?: 'If this was not you, please telephone the chamber.' }}
                            </x-ui.alert>
                        @elseif ($appointment->status === AppointmentStatus::Rescheduled && $appointment->rescheduledTo)
                            <x-ui.alert tone="info" title="This appointment was moved">
                                Your new time is
                                <a href="{{ route('patient.appointments.show', $appointment->rescheduledTo) }}"
                                   class="font-semibold underline">{{ $appointment->rescheduledTo->dateLabel() }},
                                    {{ $appointment->rescheduledTo->startsAtLocal()->format('g:i A') }}</a>.
                            </x-ui.alert>
                        @endif

                        <dl class="grid gap-4 border-t border-line pt-6 sm:grid-cols-2">
                            <div class="flex flex-col gap-0.5">
                                <dt class="text-sm text-muted">Booking number</dt>
                                <dd class="font-semibold tabular-nums text-ink">{{ $appointment->reference }}</dd>
                            </div>

                            @if ($fee = $appointment->formattedFee())
                                <div class="flex flex-col gap-0.5">
                                    <dt class="text-sm text-muted">Consultation fee</dt>
                                    <dd class="flex items-center gap-2 font-semibold text-ink">
                                        {{ $fee }}
                                        @if ($appointment->payment_status === PaymentStatus::Paid)
                                            <x-ui.badge tone="positive">Paid</x-ui.badge>
                                        @endif
                                    </dd>
                                </div>
                            @endif

                            @if ($appointment->seat_no > 1)
                                <div class="flex flex-col gap-0.5">
                                    <dt class="text-sm text-muted">Your number</dt>
                                    <dd class="font-semibold text-ink">{{ $appointment->seat_no }}</dd>
                                </div>
                            @endif

                            @if ($appointment->notes)
                                <div class="flex flex-col gap-0.5 sm:col-span-2">
                                    <dt class="text-sm text-muted">What you told us</dt>
                                    <dd class="leading-relaxed text-ink">{{ $appointment->notes }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if ($doctor->booking_instructions && $appointment->status->isActive())
                            <div class="rounded-xl border border-line bg-paper-shade p-4 text-[0.9375rem] leading-relaxed text-muted">
                                {{ $doctor->booking_instructions }}
                            </div>
                        @endif
                    </div>
                </x-ui.card>

                {{-- Documents from this visit --}}
                @if ($appointment->documents->isNotEmpty())
                    <x-ui.card>
                        <div class="flex flex-col gap-4">
                            <h2 class="text-lg font-semibold text-ink">From this visit</h2>

                            @foreach ($appointment->documents as $document)
                                <a href="{{ route('documents.download', $document) }}"
                                   class="group flex items-center gap-3 rounded-xl border border-line p-3 transition-colors hover:border-line-strong hover:bg-paper-shade">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand" aria-hidden="true">
                                        <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                    </span>

                                    <span class="flex min-w-0 flex-1 flex-col">
                                        <span class="truncate font-medium text-ink group-hover:text-brand">{{ $document->title }}</span>
                                        <span class="text-xs text-muted">{{ $document->kind->getLabel() }} · {{ $document->formattedSize() }}</span>
                                    </span>

                                    <span class="text-sm font-semibold text-brand">Download</span>
                                </a>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif
            </div>

            {{-- Actions --}}
            <aside class="flex flex-col gap-6">
                @if ($needsPayment)
                    <x-ui.card>
                        <div class="flex flex-col gap-3">
                            <h2 class="text-lg font-semibold text-ink">Pay in advance</h2>
                            <p class="text-[0.9375rem] leading-relaxed text-muted">
                                Settle the {{ $appointment->formattedFee() }} fee now, or pay at the chamber on the day.
                            </p>

                            <form method="POST" action="{{ route('payments.start', $appointment) }}">
                                @csrf
                                <x-ui.button type="submit" block>Pay now</x-ui.button>
                            </form>
                        </div>
                    </x-ui.card>
                @endif

                <x-ui.card>
                    <div class="flex flex-col gap-3">
                        <h2 class="text-lg font-semibold text-ink">Need to change it?</h2>

                        @if ($canCancel)
                            <p class="text-[0.9375rem] leading-relaxed text-muted">
                                You can cancel online until
                                <strong class="text-ink">{{ $workflow->cancellationDeadline($appointment)->format('g:i A on l j F') }}</strong>.
                            </p>

                            {{-- A confirm step, because there is no undo and the
                                 slot is released to somebody else immediately. --}}
                            <div x-data="{ confirming: false }" class="flex flex-col gap-3">
                                <x-ui.button variant="secondary" x-show="! confirming" @click="confirming = true">
                                    Cancel this appointment
                                </x-ui.button>

                                <form x-cloak x-show="confirming"
                                      method="POST"
                                      action="{{ route('patient.appointments.cancel', $appointment) }}"
                                      class="flex flex-col gap-3">
                                    @csrf

                                    <x-ui.field
                                        name="reason"
                                        label="Why are you cancelling? (optional)"
                                        as="textarea"
                                        :rows="3" />

                                    <div class="flex gap-2">
                                        <x-ui.button type="submit" variant="danger" size="sm" class="flex-1">
                                            Yes, cancel it
                                        </x-ui.button>
                                        <x-ui.button variant="ghost" size="sm" @click.prevent="confirming = false">
                                            Keep it
                                        </x-ui.button>
                                    </div>
                                </form>
                            </div>
                        @elseif ($appointment->status->isActive())
                            <p class="text-[0.9375rem] leading-relaxed text-muted">
                                It is now too close to your appointment to cancel online. Please telephone the chamber.
                            </p>
                        @else
                            <p class="text-[0.9375rem] leading-relaxed text-muted">
                                This appointment is closed. You are welcome to book another.
                            </p>
                        @endif

                        @if ($doctor->telHref())
                            <x-ui.button :href="$doctor->telHref()" variant="ghost" size="sm" class="w-fit">
                                {{ $doctor->phone }}
                            </x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            </aside>
        </div>
    </x-patient.shell>
</x-layouts.app>
