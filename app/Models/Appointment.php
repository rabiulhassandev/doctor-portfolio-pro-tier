<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A booked appointment.
 *
 * >>> DO NOT WRITE `status` DIRECTLY. <<<
 *
 * Every status change goes through App\Services\Booking\AppointmentWorkflow,
 * which validates the transition, records who did it, and sends the right
 * emails. Calling `$appointment->update(['status' => …])` from a controller
 * skips all three and is how an audit trail quietly stops being true.
 *
 * @property string $reference
 * @property int $patient_id
 * @property string $patient_name
 * @property string|null $patient_email
 * @property string $patient_phone
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $seat_no
 * @property int $slot_guard
 * @property AppointmentStatus $status
 * @property Carbon|null $hold_expires_at
 * @property string|null $notes
 * @property string|null $admin_notes
 * @property string|null $fee_amount
 * @property string|null $currency
 * @property PaymentStatus|null $payment_status
 * @property int|null $rescheduled_to_id
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'hold_expires_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'reminded_at' => 'immutable_datetime',
            'status' => AppointmentStatus::class,
            'payment_status' => PaymentStatus::class,
            'seat_no' => 'integer',
            'fee_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $appointment): void {
            if (blank($appointment->reference)) {
                $appointment->reference = static::generateReference();
            }
        });

        /*
         | Keep `slot_guard` in step with the status.
         |
         | This is what makes the unique index on (starts_at, seat_no,
         | slot_guard) mean "one LIVE booking per seat" rather than "one booking
         | per seat, ever". See the long comment in the appointments migration.
         |
         |   holds the seat  →  0        (all live bookings collide here)
         |   released it     →  own id   (unique to this row, collides with nothing)
         |
         | Pure normalisation derived from another column, which is exactly what
         | a saving() hook is for — unlike the notifications, which have side
         | effects and therefore live in an explicit service call.
         |
         | On create the id does not exist yet, so a released row is stamped
         | again once it does. In practice a booking is never born cancelled,
         | but a seeder is entitled to make one.
         */
        static::saving(function (self $appointment): void {
            $appointment->slot_guard = $appointment->status?->holdsSeat()
                ? 0
                : (int) ($appointment->id ?? 0);
        });

        static::created(function (self $appointment): void {
            if (! $appointment->status->holdsSeat() && $appointment->slot_guard === 0) {
                $appointment->newQuery()
                    ->whereKey($appointment->getKey())
                    ->update(['slot_guard' => $appointment->getKey()]);
            }
        });
    }

    /**
     * A short, unguessable booking number: "APT-8F2K9Q".
     *
     * Excludes the characters people misread when a receptionist reads one down
     * the phone — no O against 0, no I or L against 1.
     */
    public static function generateReference(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = collect(range(1, 6))
                ->map(fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');

            $reference = 'APT-'.$code;
        } while (static::query()->where('reference', $reference)->exists());

        return $reference;
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The payment that actually settled, if any.
     *
     * A patient may make several attempts; only one of them took the money.
     *
     * @return HasOne<Payment, $this>
     */
    public function successfulPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->where('status', PaymentStatus::Paid->value);
    }

    /** @return HasMany<MedicalDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(MedicalDocument::class);
    }

    /** @return HasMany<AppointmentStatusLog, $this> */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(AppointmentStatusLog::class)->latest('created_at');
    }

    /** The appointment this one was moved to, when it was rescheduled. */
    /** @return BelongsTo<Appointment, $this> */
    public function rescheduledTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_to_id');
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    /** Appointments that count against a slot's capacity. */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', AppointmentStatus::blockingValues());
    }

    /**
     * Bookings whose payment window has lapsed and whose seat should be freed.
     *
     * Only pending ones: a confirmed appointment keeps its seat whatever the
     * hold column says.
     */
    public function scopeExpiredHolds(Builder $query): void
    {
        $query->where('status', AppointmentStatus::Pending->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now());
    }

    /** Excludes bookings that are only being held while a payment completes. */
    public function scopeNotExpiredHold(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now());
        });
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now())->orderBy('starts_at');
    }

    public function scopePast(Builder $query): void
    {
        $query->where('starts_at', '<', now())->orderByDesc('starts_at');
    }

    public function scopeOnDate(Builder $query, CarbonImmutable $date): void
    {
        // The date is a clinic-time day; the column is UTC. Convert the day's
        // boundaries rather than comparing dates, or an evening appointment
        // lands on the wrong day for any clinic east of Greenwich.
        $query->whereBetween('starts_at', [
            $date->startOfDay()->utc(),
            $date->endOfDay()->utc(),
        ]);
    }

    public function scopeToday(Builder $query): void
    {
        $query->onDate(Clock::today());
    }

    // -----------------------------------------------------------------------
    // Presentation
    // -----------------------------------------------------------------------

    /** The appointment time on the clinic's own clock. */
    public function startsAtLocal(): CarbonImmutable
    {
        return Clock::fromStorage($this->starts_at);
    }

    public function endsAtLocal(): CarbonImmutable
    {
        return Clock::fromStorage($this->ends_at);
    }

    /** "Sunday, 12 September 2026" */
    public function dateLabel(): string
    {
        return $this->startsAtLocal()->format('l, j F Y');
    }

    /** "6:00 PM – 6:30 PM" */
    public function timeLabel(): string
    {
        return $this->startsAtLocal()->format('g:i A').' – '.$this->endsAtLocal()->format('g:i A');
    }

    public function isPast(): bool
    {
        return $this->starts_at->isPast();
    }

    /** Shown to the patient as "upcoming" rather than as history. */
    public function isUpcoming(): bool
    {
        return ! $this->isPast() && $this->status->isActive();
    }

    /**
     * Whether the patient may still call this off themselves.
     *
     * Two conditions: the appointment is in a state that can be cancelled, and
     * there is still enough notice. Inside the cutoff the dashboard shows a
     * "please ring the chamber" note instead — a late no-show the clinic knew
     * about is a slot they could have offered someone else.
     */
    public function isCancellableByPatient(): bool
    {
        if (! $this->status->canTransitionTo(AppointmentStatus::Cancelled)) {
            return false;
        }

        $cutoff = Clock::now()->addHours((int) config('booking.cancellation_cutoff_hours', 12));

        return $this->startsAtLocal()->greaterThan($cutoff);
    }

    /** True when money is still owed for this appointment. */
    public function isUnpaid(): bool
    {
        return $this->payment_status !== PaymentStatus::Paid;
    }

    /** "৳ 1,500" — or null when the practice does not publish a fee. */
    public function formattedFee(): ?string
    {
        if ($this->fee_amount === null || (float) $this->fee_amount <= 0) {
            return null;
        }

        return trim(($this->currency ?? '').' '.number_format((float) $this->fee_amount, 2));
    }

    /**
     * Write the status column.
     *
     * @internal Called only by App\Services\Booking\AppointmentWorkflow. Every
     *           other caller must go through that class so the transition is
     *           validated, logged and announced.
     */
    public function markStatus(AppointmentStatus $status): void
    {
        $this->status = $status;
    }

    /** The patient's initials, for the avatar on the admin appointment list. */
    public function patientInitials(): string
    {
        return Str::of($this->patient_name)
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
