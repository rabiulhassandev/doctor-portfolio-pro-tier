<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\BookingActor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in an appointment's history.
 *
 * Written only by App\Services\Booking\AppointmentWorkflow. Never updated —
 * a log that can be edited is not a log.
 *
 * @property int $appointment_id
 * @property AppointmentStatus|null $from_status
 * @property AppointmentStatus $to_status
 * @property BookingActor $actor
 * @property int|null $user_id
 * @property string|null $reason
 */
class AppointmentStatusLog extends Model
{
    /** Only created_at is meaningful; there is no such thing as editing history. */
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'from_status' => AppointmentStatus::class,
            'to_status' => AppointmentStatus::class,
            'actor' => BookingActor::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** The staff member responsible, when there was one. */
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A one-line summary for the history panel in the admin view.
     *
     * "The clinic confirmed this appointment" reads better in a timeline than
     * "pending → confirmed", and is what a receptionist actually needs.
     */
    public function summary(): string
    {
        $who = $this->user?->name ?? $this->actor->label();

        return $this->from_status === null
            ? sprintf('%s created this appointment', $who)
            : sprintf('%s changed it to "%s"', $who, $this->to_status->getLabel());
    }
}
