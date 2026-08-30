<?php

namespace App\Models;

use App\Enums\DocumentKind;
use Database\Factories\MedicalDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * A prescription, report or invoice issued to a patient.
 *
 * >>> THERE IS NO url() METHOD ON THIS MODEL, AND THERE MUST NOT BE ONE. <<<
 *
 * The file lives on the private `medical` disk, outside public/ and with
 * `serve => false`, so it has no URL to give. The only way to the contents is
 * App\Http\Controllers\MedicalDocumentController, which authorises the request
 * first. Adding a convenience accessor here that returned a path would quietly
 * undo that, so the omission is deliberate.
 *
 * @property string $ulid
 * @property int $patient_id
 * @property int|null $appointment_id
 * @property string $title
 * @property DocumentKind $kind
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property bool $is_visible_to_patient
 */
class MedicalDocument extends Model
{
    /** @use HasFactory<MedicalDocumentFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'is_visible_to_patient' => 'boolean',
            'size_bytes' => 'integer',
            'downloaded_at' => 'immutable_datetime',
            'download_count' => 'integer',
        ];
    }

    /**
     * HasUlids would otherwise try to make the primary key a ULID. Here the
     * primary key stays a normal auto-increment and only this column is a ULID.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** The download URL carries the ULID, never the sequential id. */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /** Documents the patient is allowed to see. */
    public function scopeVisibleToPatient(Builder $query): void
    {
        $query->where('is_visible_to_patient', true);
    }

    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }

    /** Whether the file is actually still on disk. */
    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /** "248 KB" */
    public function formattedSize(): string
    {
        return Number::fileSize($this->size_bytes, precision: 0);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /** Record that the patient collected it. The clinic always asks. */
    public function recordDownload(): void
    {
        $this->forceFill([
            'downloaded_at' => now(),
            'download_count' => $this->download_count + 1,
        ])->saveQuietly();
    }
}
