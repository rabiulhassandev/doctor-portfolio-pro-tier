@php
    use App\Livewire\BookingWizard;
    use App\Support\Clock;

    $steps = [
        BookingWizard::STEP_DATE => 'Date',
        BookingWizard::STEP_TIME => 'Time',
        BookingWizard::STEP_DETAILS => 'Your details',
        BookingWizard::STEP_PAYMENT => 'Payment',
    ];

    $patient = auth('patient')->user();
    $booked = $this->bookedAppointment();
@endphp

<div class="mx-auto max-w-3xl">

    {{-- =============================================================
         Confirmed. Everything above is finished with.
         ============================================================= --}}
    @if ($booked && ! $handingOffToGateway)
        <div class="flex flex-col items-center gap-6 text-center" data-reveal>
            <span class="flex size-16 items-center justify-center rounded-[3px] bg-positive-soft text-positive" aria-hidden="true">
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </span>

            <div class="flex flex-col gap-2">
                <h2 class="text-3xl text-ink sm:text-4xl">
                    {{ $booked->status->isActive() && $booked->status->value === 'confirmed'
                        ? 'Your appointment is confirmed'
                        : 'We have your booking' }}
                </h2>
                <p class="text-lg leading-relaxed text-muted">
                    {{ $booked->status->value === 'confirmed'
                        ? 'We have emailed you the details.'
                        : 'The chamber will confirm shortly, and we will email you as soon as they do.' }}
                </p>
            </div>

            <x-ui.card padding="loose" class="w-full text-left">
                <dl class="grid gap-5 sm:grid-cols-2">
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-sm text-muted">When</dt>
                        <dd class="text-lg font-semibold text-ink">{{ $booked->dateLabel() }}</dd>
                        <dd class="tabular-nums text-ink">{{ $booked->timeLabel() }}</dd>
                    </div>

                    <div class="flex flex-col gap-0.5">
                        <dt class="text-sm text-muted">Booking number</dt>
                        <dd class="text-lg font-semibold tabular-nums text-ink">{{ $booked->reference }}</dd>
                    </div>

                    @if ($fee = $booked->formattedFee())
                        <div class="flex flex-col gap-0.5">
                            <dt class="text-sm text-muted">Fee</dt>
                            <dd class="font-semibold text-ink">
                                {{ $fee }}
                                <span class="font-normal text-muted">
                                    {{ $booked->isUnpaid() ? '— payable at the chamber' : '— paid, thank you' }}
                                </span>
                            </dd>
                        </div>
                    @endif

                    @if ($address = $this->doctor()->fullAddress())
                        <div class="flex flex-col gap-0.5">
                            <dt class="text-sm text-muted">Where</dt>
                            <dd class="leading-relaxed text-ink">{{ $address }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($this->doctor()->booking_instructions)
                    <p class="mt-5 rounded-[3px] border border-line bg-paper-shade p-4 text-[0.9375rem] leading-relaxed text-muted">
                        {{ $this->doctor()->booking_instructions }}
                    </p>
                @endif
            </x-ui.card>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <x-ui.button :href="route('patient.appointments.show', $booked)">View this appointment</x-ui.button>
                <x-ui.button :href="route('patient.dashboard')" variant="secondary">My account</x-ui.button>
            </div>
        </div>

    {{-- =============================================================
         Handing off to the payment gateway.
         ============================================================= --}}
    @elseif ($booked && $handingOffToGateway)
        <div class="flex flex-col items-center gap-5 py-10 text-center">
            <span class="flex size-14 items-center justify-center rounded-[3px] bg-brass-soft text-ink" aria-hidden="true">
                <svg class="size-7 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
                </svg>
            </span>

            <div class="flex flex-col gap-2">
                <h2 class="text-2xl text-ink">Taking you to payment</h2>
                <p class="leading-relaxed text-muted">
                    Your appointment is held. Please do not close this window.
                </p>
            </div>

            {{-- A real POST with a CSRF token, not a Livewire redirect: starting
                 a payment changes state and must not be repeatable by a refresh. --}}
            <form id="start-payment" method="POST" action="{{ route('payments.start', $booked) }}">
                @csrf
                <input type="hidden" name="gateway" value="{{ $gateway }}">
                <x-ui.button type="submit" size="lg">Continue to payment</x-ui.button>
            </form>

            <script>document.getElementById('start-payment')?.submit();</script>
        </div>

    {{-- =============================================================
         The wizard itself.
         ============================================================= --}}
    @else
        {{-- Progress --}}
        <ol class="mb-8 flex items-center gap-2" aria-label="Booking progress">
            @foreach ($steps as $number => $label)
                @php
                    $isDone = $step > $number;
                    $isCurrent = $step === $number;
                @endphp

                <li class="flex flex-1 items-center gap-2">
                    <span @class([
                        'flex size-7 shrink-0 items-center justify-center rounded-[3px] text-xs font-semibold transition-colors',
                        'bg-night text-white' => $isCurrent,
                        'bg-brass text-white' => $isDone,
                        'bg-paper-shade text-muted' => ! $isCurrent && ! $isDone,
                    ])>
                        @if ($isDone)
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        @else
                            {{ $number }}
                        @endif
                    </span>

                    <span @class([
                        'hidden text-sm font-medium sm:inline',
                        'text-ink' => $isCurrent,
                        'text-muted' => ! $isCurrent,
                    ])>{{ $label }}</span>

                    @unless ($loop->last)
                        <span @class([
                            'h-px flex-1 transition-colors',
                            'bg-brass' => $isDone,
                            'bg-line' => ! $isDone,
                        ])></span>
                    @endunless
                </li>
            @endforeach
        </ol>

        @if ($errorMessage)
            <div class="mb-6">
                <x-ui.alert tone="caution">{{ $errorMessage }}</x-ui.alert>
            </div>
        @endif

        <x-ui.card padding="loose">
            {{-- ---------------------------------------------------------
                 Step 1 — pick a date
                 --------------------------------------------------------- --}}
            @if ($step === BookingWizard::STEP_DATE)
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-1">
                        <h2 class="text-2xl text-ink">When would suit you?</h2>
                        <p class="text-muted">These are the days the doctor still has appointments free.</p>
                    </div>

                    @if ($this->availableDates()->isEmpty())
                        <x-ui.empty-state
                            title="No appointments available"
                            description="There are no free times in the booking window at the moment. Please telephone the chamber and they will help.">
                            @if ($this->doctor()->telHref())
                                <x-ui.button :href="$this->doctor()->telHref()">{{ $this->doctor()->phone }}</x-ui.button>
                            @endif
                        </x-ui.empty-state>
                    @else
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($this->availableDates() as $date)
                                <button type="button"
                                        wire:key="date-{{ $date->toDateString() }}"
                                        wire:click="selectDate('{{ $date->toDateString() }}')"
                                        @class([
                                            'card-lift flex flex-col items-center gap-0.5 rounded-[3px] border px-3 py-4 text-center transition-colors',
                                            'border-brass bg-brass-soft text-ink' => $selectedDate === $date->toDateString(),
                                            'border-line bg-surface text-ink hover:border-brass hover:bg-brass-soft/40' => $selectedDate !== $date->toDateString(),
                                        ])>
                                    <span class="text-[0.6875rem] font-semibold uppercase tracking-[0.1em] text-muted">
                                        {{ $date->isToday() ? 'Today' : ($date->isTomorrow() ? 'Tomorrow' : $date->format('D')) }}
                                    </span>
                                    <span class="font-display text-2xl leading-none">{{ $date->format('j') }}</span>
                                    <span class="text-xs text-muted">{{ $date->format('M') }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

            {{-- ---------------------------------------------------------
                 Step 2 — pick a time
                 --------------------------------------------------------- --}}
            @elseif ($step === BookingWizard::STEP_TIME)
                <div class="flex flex-col gap-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <h2 class="text-2xl text-ink">Choose a time</h2>
                            <p class="text-muted">{{ Clock::parse($selectedDate)->format('l, j F Y') }}</p>
                        </div>

                        <button type="button" wire:click="backToDates"
                                class="link-underline text-sm font-medium text-ink">
                            Change the date
                        </button>
                    </div>

                    @if ($this->slots()->isEmpty())
                        <x-ui.alert tone="caution">
                            Those times have just been taken. Please pick another day.
                        </x-ui.alert>
                    @else
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($this->slots() as $slot)
                                <button type="button"
                                        wire:key="slot-{{ $slot->key() }}"
                                        wire:click="selectSlot('{{ $slot->key() }}')"
                                        class="card-lift flex flex-col items-center gap-1 rounded-[3px] border border-line bg-surface px-3 py-4 text-center transition-colors hover:border-brass hover:bg-brass-soft/40">
                                    <span class="text-lg font-semibold tabular-nums text-ink">{{ $slot->label() }}</span>

                                    @if ($scarcity = $slot->scarcityLabel())
                                        {{-- Only shown when a slot is genuinely
                                             filling up — a counter on every time
                                             reads as a pressure tactic. --}}
                                        <span class="text-xs font-medium text-caution">{{ $scarcity }}</span>
                                    @else
                                        <span class="text-xs text-muted">{{ $slot->durationMinutes() }} min</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

            {{-- ---------------------------------------------------------
                 Step 3 — who are you
                 --------------------------------------------------------- --}}
            @elseif ($step === BookingWizard::STEP_DETAILS)
                <div class="flex flex-col gap-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            <h2 class="text-2xl text-ink">Almost there</h2>
                            @if ($this->chosenSlot())
                                <p class="text-muted">
                                    {{ Clock::parse($selectedSlot)->format('l, j F') }} at
                                    <strong class="text-ink">{{ $this->chosenSlot()->label() }}</strong>
                                </p>
                            @endif
                        </div>

                        <button type="button" wire:click="backToTimes"
                                class="link-underline text-sm font-medium text-ink">
                            Change the time
                        </button>
                    </div>

                    @if (! $patient)
                        {{-- The one place a guest is stopped. The chosen slot is
                             already parked in the session, so they come back to
                             exactly this point. --}}
                        <div class="flex flex-col gap-4 rounded-[3px] border border-line bg-paper-shade p-5">
                            <div class="flex flex-col gap-1">
                                <h3 class="font-semibold text-ink">Sign in to confirm</h3>
                                <p class="text-[0.9375rem] leading-relaxed text-muted">
                                    We hold your time while you do. An account also lets you see your
                                    appointments and collect prescriptions later.
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <x-ui.button :href="route('patient.register')">Create an account</x-ui.button>
                                <x-ui.button :href="route('patient.login')" variant="secondary">I already have one</x-ui.button>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-3 rounded-[3px] border border-line bg-paper-shade p-4">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-[3px] bg-brass-soft font-semibold text-ink" aria-hidden="true">
                                    {{ $patient->initials() }}
                                </span>
                                <div class="flex min-w-0 flex-col">
                                    <span class="truncate font-semibold text-ink">{{ $patient->name }}</span>
                                    <span class="truncate text-sm text-muted">{{ $patient->phone }} · {{ $patient->email }}</span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="booking-notes" class="text-sm font-semibold text-ink">
                                    Anything the doctor should know? <span class="font-normal text-muted">(optional)</span>
                                </label>
                                <textarea id="booking-notes"
                                          wire:model="notes"
                                          rows="3"
                                          placeholder="For example: I have been short of breath climbing stairs."
                                          class="w-full resize-y rounded-[3px] border border-line-strong bg-surface px-4 py-3 text-ink placeholder:text-muted/60 focus:border-brass focus:outline-2 focus:outline-offset-2 focus:outline-brass/40"></textarea>
                                @error('notes') <p class="text-sm text-negative">{{ $message }}</p> @enderror
                            </div>

                            <x-ui.button wire:click="continueToPayment" wire:loading.attr="disabled" size="lg" block>
                                <span wire:loading.remove wire:target="continueToPayment">
                                    {{ $this->offersPaymentChoice() ? 'Continue' : 'Confirm my appointment' }}
                                </span>
                                <span wire:loading wire:target="continueToPayment">Just a moment…</span>
                            </x-ui.button>
                        </div>
                    @endif
                </div>

            {{-- ---------------------------------------------------------
                 Step 4 — how would you like to pay
                 --------------------------------------------------------- --}}
            @elseif ($step === BookingWizard::STEP_PAYMENT)
                <div class="flex flex-col gap-6">
                    <div class="flex flex-col gap-1">
                        <h2 class="text-2xl text-ink">How would you like to pay?</h2>
                        <p class="text-muted">
                            The consultation fee is
                            <strong class="text-ink">{{ config('booking.payment.currency', 'BDT') }}
                                {{ number_format((float) $this->doctor()->consultation_fee, 2) }}</strong>.
                        </p>
                    </div>

                    <fieldset class="flex flex-col gap-3">
                        <legend class="sr-only">Payment method</legend>

                        @foreach ($this->gateways() as $driver)
                            <label wire:key="gateway-{{ $driver->name() }}"
                                   @class([
                                       'flex cursor-pointer items-start gap-3 rounded-[3px] border p-4 transition-colors',
                                       'border-brass bg-brass-soft' => $gateway === $driver->name(),
                                       'border-line hover:border-line-strong hover:bg-paper-shade' => $gateway !== $driver->name(),
                                   ])>
                                <input type="radio"
                                       wire:model.live="gateway"
                                       value="{{ $driver->name() }}"
                                       name="gateway"
                                       class="mt-1 size-4 border-line-strong text-ink focus:outline-2 focus:outline-offset-2 focus:outline-brass/40">

                                <span class="flex flex-col gap-0.5">
                                    <span class="font-semibold text-ink">{{ $driver->label() }}</span>
                                    <span class="text-sm leading-relaxed text-muted">
                                        {{ $driver->name() === \App\Services\Payments\PaymentManager::PAY_AT_CLINIC
                                            ? 'Settle up in person on the day of your appointment.'
                                            : 'You will be taken to a secure payment page.' }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>

                    <div class="flex flex-col gap-3">
                        <x-ui.button wire:click="confirm" wire:loading.attr="disabled" size="lg" block>
                            <span wire:loading.remove wire:target="confirm">Confirm my appointment</span>
                            <span wire:loading wire:target="confirm">Booking your appointment…</span>
                        </x-ui.button>

                        <button type="button" wire:click="backToTimes"
                                class="link-underline mx-auto text-sm font-medium text-muted hover:text-ink">
                            Go back
                        </button>
                    </div>
                </div>
            @endif
        </x-ui.card>
    @endif
</div>
