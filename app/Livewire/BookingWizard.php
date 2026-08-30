<?php

namespace App\Livewire;

use App\Enums\PaymentStatus;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingData;
use App\Services\Booking\BookingService;
use App\Services\Payments\PaymentManager;
use App\Support\Clock;
use App\Support\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The booking flow.
 *
 * Four steps: pick a date, pick a time, say who you are, choose how to pay.
 *
 * ===========================================================================
 * WHY THE CHOSEN SLOT LIVES IN THE SESSION
 * ===========================================================================
 *
 * A guest may browse and choose a time before they have an account. Asking them
 * to sign in means a full page navigation away and back, which destroys the
 * component's state — so the chosen slot is written to the session, survives
 * the round trip, and is picked up again in mount(). Without that, a patient
 * returns from registering to an empty calendar and has to find their time
 * again, which is exactly the moment people give up.
 *
 * ===========================================================================
 * WHAT THIS COMPONENT IS NOT ALLOWED TO DECIDE
 * ===========================================================================
 *
 * Whether a slot is really free, what it costs, and how long it lasts. All
 * three are re-derived server-side by BookingService at the moment of booking.
 * Everything here is presentation: a hostile user editing the Livewire payload
 * can change what they *see*, never what they get.
 */
class BookingWizard extends Component
{
    /** Where the chosen slot is parked across the sign-in round trip. */
    private const SESSION_KEY = 'booking.slot';

    public const STEP_DATE = 1;

    public const STEP_TIME = 2;

    public const STEP_DETAILS = 3;

    public const STEP_PAYMENT = 4;

    public int $step = self::STEP_DATE;

    /** 'Y-m-d' */
    public ?string $selectedDate = null;

    /** 'Y-m-d H:i' — the only thing the form ever posts about a slot. */
    public ?string $selectedSlot = null;

    public string $notes = '';

    /** The config key of the chosen gateway, or 'cash'. */
    public ?string $gateway = null;

    /** Set once the booking exists, so the final screen can show it. */
    public ?string $bookedReference = null;

    /**
     * True when the booking is made and the browser should now post itself to
     * the payment gateway. The view renders an auto-submitting form.
     */
    public bool $handingOffToGateway = false;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        // Coming back from signing in or registering.
        if ($slot = session(self::SESSION_KEY)) {
            $this->selectedSlot = $slot;
            $this->selectedDate = Clock::parse($slot)->toDateString();

            // Still free? Somebody may have taken it while they registered.
            if ($this->resolveSlot() === null) {
                $this->forgetHeldSlot();
                $this->errorMessage = 'Sorry — that time was taken while you were signing in. Please choose another.';
                $this->step = self::STEP_TIME;

                return;
            }

            $this->step = Auth::guard('patient')->check() ? self::STEP_DETAILS : self::STEP_TIME;
        }

        $this->gateway = $this->defaultGateway();
    }

    // -----------------------------------------------------------------------
    // Data for the view
    // -----------------------------------------------------------------------

    /**
     * The dates that still have a place free, grouped for the date strip.
     *
     * @return Collection<int, CarbonImmutable>
     */
    #[Computed]
    public function availableDates(): Collection
    {
        return app(AvailabilityService::class)->bookableDates();
    }

    /**
     * The times free on the chosen date.
     *
     * Recomputed on every render rather than cached, deliberately: a slot list
     * that is even a minute stale is how two patients are shown the same last
     * remaining appointment.
     *
     * @return Collection<int, Slot>
     */
    #[Computed]
    public function slots(): Collection
    {
        if (blank($this->selectedDate)) {
            return collect();
        }

        return app(AvailabilityService::class)->slotsForDate(Clock::parse($this->selectedDate));
    }

    #[Computed]
    public function chosenSlot(): ?Slot
    {
        return $this->resolveSlot();
    }

    #[Computed]
    public function doctor(): DoctorProfile
    {
        return DoctorProfile::current();
    }

    /** The payment options actually available, for the checkout radio. */
    #[Computed]
    public function gateways(): Collection
    {
        if (! config('booking.payment.enabled')) {
            return collect();
        }

        return app(PaymentManager::class)->available();
    }

    #[Computed]
    public function bookedAppointment(): ?Appointment
    {
        return $this->bookedReference
            ? Appointment::query()->where('reference', $this->bookedReference)->first()
            : null;
    }

    // -----------------------------------------------------------------------
    // Steps
    // -----------------------------------------------------------------------

    public function selectDate(string $date): void
    {
        $this->errorMessage = null;
        $this->selectedDate = $date;
        $this->selectedSlot = null;

        unset($this->slots);

        $this->step = self::STEP_TIME;
    }

    public function selectSlot(string $slotKey): void
    {
        $this->errorMessage = null;

        // Cheap sanity check so an obviously stale click is caught before the
        // patient is asked to sign in for a slot that has gone.
        if (app(AvailabilityService::class)->findBookableSlot(Clock::parse($slotKey)) === null) {
            $this->errorMessage = 'Sorry — that time has just been taken. Please choose another.';
            unset($this->slots);

            return;
        }

        $this->selectedSlot = $slotKey;

        /*
         | Park it before doing anything that might navigate away. A guest is
         | about to be sent to the sign-in page, and this is what brings them
         | back to the same slot afterwards.
         */
        session([self::SESSION_KEY => $slotKey]);

        $this->step = self::STEP_DETAILS;
    }

    public function backToDates(): void
    {
        $this->errorMessage = null;
        $this->step = self::STEP_DATE;
    }

    public function backToTimes(): void
    {
        $this->errorMessage = null;
        $this->selectedSlot = null;
        $this->forgetHeldSlot();

        unset($this->slots);

        $this->step = self::STEP_TIME;
    }

    /** Move from "your details" to the payment choice. */
    public function continueToPayment(): void
    {
        $this->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! Auth::guard('patient')->check()) {
            return;   // The view shows the sign-in prompt instead.
        }

        if (! $this->needsPaymentStep()) {
            $this->confirm();

            return;
        }

        $this->step = self::STEP_PAYMENT;
    }

    /**
     * Whether there is a genuine choice to make about paying.
     *
     * There is not, in three cases: no fee, payments switched off, or the only
     * option being "pay at the chamber". That last one matters — a checkout
     * step showing a single radio button the patient cannot vary is an extra
     * click that asks them to make a decision they do not have.
     */
    /** The same question, for the view to label its button correctly. */
    #[Computed]
    public function offersPaymentChoice(): bool
    {
        return $this->needsPaymentStep();
    }

    private function needsPaymentStep(): bool
    {
        if (! $this->doctor()->hasFee()) {
            return false;
        }

        $available = $this->gateways();

        if ($available->isEmpty()) {
            return false;
        }

        return ! ($available->count() === 1
            && $available->first()->name() === PaymentManager::PAY_AT_CLINIC);
    }

    /**
     * Create the appointment.
     *
     * Everything that matters happens inside BookingService: the slot is
     * re-derived, the seat allocated under a lock, and the unique index has the
     * final word. This method only translates the outcome into something the
     * patient can read.
     */
    public function confirm(): void
    {
        $patient = Auth::guard('patient')->user();

        if (! $patient) {
            $this->errorMessage = 'Please sign in to confirm your appointment.';

            return;
        }

        if (blank($this->selectedSlot)) {
            $this->errorMessage = 'Please choose a time first.';
            $this->step = self::STEP_DATE;

            return;
        }

        $isOnlinePayment = $this->gateway !== null
            && $this->gateway !== PaymentManager::PAY_AT_CLINIC
            && $this->gateways()->isNotEmpty()
            && $this->doctor()->hasFee();

        try {
            $appointment = app(BookingService::class)->book(
                BookingData::fromForm($patient, $this->selectedSlot, $this->notes ?: null),
                // Hold the seat only for as long as the gateway page needs it.
                holdForPayment: $isOnlinePayment,
            );
        } catch (SlotUnavailableException $e) {
            // Expected — somebody else got there first. The message is already
            // written for a patient to read.
            $this->errorMessage = $e->getMessage();
            $this->forgetHeldSlot();
            $this->selectedSlot = null;
            unset($this->slots);
            $this->step = self::STEP_TIME;

            return;
        }

        $this->forgetHeldSlot();
        $this->bookedReference = $appointment->reference;

        /*
         | Paying online means leaving this page for the gateway.
         |
         | The hand-off is a real HTML form that the view submits on load,
         | rather than a Livewire redirect: starting a payment changes state and
         | belongs behind a POST with a CSRF token, not behind a GET that a
         | refresh or a prefetching browser could fire again.
         */
        if ($isOnlinePayment) {
            $this->handingOffToGateway = true;

            return;
        }

        $appointment->forceFill(['payment_status' => PaymentStatus::DueAtClinic])->save();
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function resolveSlot(): ?Slot
    {
        if (blank($this->selectedSlot)) {
            return null;
        }

        return app(AvailabilityService::class)->findBookableSlot(Clock::parse($this->selectedSlot));
    }

    private function forgetHeldSlot(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    private function defaultGateway(): ?string
    {
        $configured = config('booking.payment.default');

        return $this->gateways()->contains(fn ($driver): bool => $driver->name() === $configured)
            ? $configured
            : $this->gateways()->first()?->name();
    }

    public function render()
    {
        return view('livewire.booking-wizard');
    }
}
