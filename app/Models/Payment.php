<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One attempt to pay for an appointment.
 *
 * Nothing here knows what SSLCommerz is. The gateway drivers translate their
 * provider's vocabulary into this shape, which is what lets a buyer swap
 * processors without touching the booking code.
 *
 * @property int $appointment_id
 * @property string $gateway
 * @property string $reference
 * @property string $amount
 * @property string $currency
 * @property PaymentStatus $status
 * @property string|null $gateway_transaction_id
 * @property string|null $gateway_session_key
 * @property array|null $payload
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'payload' => 'array',
            'paid_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            /*
             | decimal:2 casts to a numeric STRING, not a float, and that is
             | deliberate. The anti-tampering check in the gateway callback
             | compares the amount the provider reports against this one, and
             | float equality is the last thing you want deciding whether a
             | patient paid the right fee. Compare with bccomp().
             */
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (blank($payment->reference)) {
                $payment->reference = static::generateReference($payment->appointment_id);
            }
        });
    }

    /**
     * Our transaction id, e.g. "APT-000123-8F2K9QAB".
     *
     * The appointment id makes it readable when reconciling against the
     * gateway's dashboard; the random tail makes it unguessable, so nobody can
     * enumerate other people's transactions or replay a reference that was
     * never issued to them.
     */
    public static function generateReference(int|string|null $appointmentId): string
    {
        return sprintf(
            'APT-%06d-%s',
            (int) $appointmentId,
            Str::upper(Str::random(8)),
        );
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePaid(Builder $query): void
    {
        $query->where('status', PaymentStatus::Paid->value);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', PaymentStatus::Pending->value);
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    /** "BDT 1,500.00" */
    public function formattedAmount(): string
    {
        return $this->currency.' '.number_format((float) $this->amount, 2);
    }

    /** The label of the gateway that took it, as configured. */
    public function gatewayLabel(): string
    {
        return config("booking.payment.gateways.{$this->gateway}.label", Str::headline($this->gateway));
    }
}
