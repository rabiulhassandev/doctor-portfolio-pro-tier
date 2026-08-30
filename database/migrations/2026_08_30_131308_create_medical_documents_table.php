<?php

use App\Models\MedicalDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prescriptions, lab reports and invoices the doctor issues to a patient.
 *
 * The files themselves live on the private `medical` disk, outside public/ and
 * unreachable by URL. Nothing here is ever turned into a link — the only way to
 * a file is App\Http\Controllers\MedicalDocumentController, which authorises
 * every request. See config/filesystems.php.
 *
 * @see MedicalDocument
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_documents', function (Blueprint $table) {
            $table->id();

            /*
             | The route key, so the download URL contains a ULID rather than a
             | sequential id. Even with authorisation on every request, an
             | enumerable URL tells an attacker how many documents exist and
             | invites them to try the next one. This costs nothing and removes
             | the question.
             */
            $table->ulid('ulid')->unique();

            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

            /*
             | Which visit this document came out of. Nullable, because a lab
             | report often arrives days after the appointment and sometimes
             | with no appointment behind it at all.
             */
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');

            // App\Enums\DocumentKind: prescription | report | invoice | other.
            $table->string('kind', 20)->default('prescription');

            /*
             | Stored per row rather than assumed, so moving future uploads to
             | S3 does not orphan every file already on local disk.
             */
            $table->string('disk', 20)->default('medical');

            // e.g. "patients/12/01J8ZC…ulid….pdf"
            $table->string('path');

            /*
             | The name to give the browser at download time. Stored separately
             | because the file on disk is named with a ULID — a hostile upload
             | filename is one of the classic routes to path traversal, and the
             | simplest defence is never to use it as a filename at all.
             */
            $table->string('original_filename');

            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             | Lets the doctor upload a document and release it later — useful
             | when a report needs reviewing before the patient sees it. A
             | staged document is invisible on the patient's dashboard and its
             | download route refuses them.
             */
            $table->boolean('is_visible_to_patient')->default(true);

            /*
             | "Did they get it?" is the first thing a chamber asks when a
             | patient rings about a missing prescription.
             */
            $table->dateTime('downloaded_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);

            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
            $table->index(['appointment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_documents');
    }
};
