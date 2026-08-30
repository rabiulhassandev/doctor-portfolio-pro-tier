<?php

namespace App\Models;

use App\Notifications\Patient\ResetPasswordNotification;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Someone who books appointments.
 *
 * Patients authenticate on their own `patient` guard, entirely separate from
 * the staff `users` table that can reach the admin panel. See config/auth.php
 * and the note on the create_patients_table migration for why.
 *
 * @property string $name
 * @property string $email
 * @property string $phone
 * @property string $password
 * @property Carbon|null $date_of_birth
 * @property string|null $gender
 * @property string|null $address
 * @property string|null $medical_notes
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 */
class Patient extends Authenticatable
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'date_of_birth',
        'gender',
        'address',
    ];

    /**
     * Never serialise these.
     *
     * `medical_notes` is on the list deliberately. It is staff-authored and can
     * hold anything from an allergy to a diagnosis, so it must not leak into a
     * JSON response or a log line just because someone dumped the model.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'medical_notes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Emails are case-insensitive in practice; storing them mixed-case
        // means "Ali@example.com" and "ali@example.com" become two accounts.
        static::saving(function (self $patient): void {
            if (filled($patient->email)) {
                $patient->email = Str::lower(trim($patient->email));
            }
        });
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasMany<MedicalDocument, $this> */
    public function medicalDocuments(): HasMany
    {
        return $this->hasMany(MedicalDocument::class);
    }

    /**
     * Documents this patient is actually allowed to see.
     *
     * The doctor can stage a report before releasing it, so "the patient's
     * documents" and "the documents the patient may read" are different sets.
     * The dashboard must always use this one.
     *
     * @return HasMany<MedicalDocument, $this>
     */
    public function visibleDocuments(): HasMany
    {
        return $this->medicalDocuments()->where('is_visible_to_patient', true);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Send the password reset link on the patient guard's own broker.
     *
     * Overridden so the email points at the patient reset form rather than at
     * the staff one — the default notification builds a URL for the `users`
     * broker, which would send patients to a page they cannot use.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** Where SMS notifications for this patient go. */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
