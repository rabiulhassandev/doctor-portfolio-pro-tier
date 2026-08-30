<?php

namespace Database\Factories;

use App\Enums\DocumentKind;
use App\Models\MedicalDocument;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MedicalDocument>
 */
class MedicalDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ulid = (string) Str::ulid();

        return [
            'patient_id' => Patient::factory(),
            'appointment_id' => null,
            'title' => 'Prescription',
            'kind' => DocumentKind::Prescription,
            'disk' => 'medical',
            // Mirrors what the controller and admin upload field produce.
            'path' => "patients/{$ulid}.pdf",
            'original_filename' => 'prescription.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128_000,
            'is_visible_to_patient' => true,
        ];
    }

    public function kind(DocumentKind $kind): static
    {
        return $this->state(fn (): array => [
            'kind' => $kind,
            'title' => $kind->getLabel(),
        ]);
    }

    /** Uploaded but not yet released to the patient. */
    public function hidden(): static
    {
        return $this->state(fn (): array => [
            'is_visible_to_patient' => false,
        ]);
    }
}
