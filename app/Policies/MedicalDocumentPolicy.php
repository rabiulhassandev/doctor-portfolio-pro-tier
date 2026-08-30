<?php

namespace App\Policies;

use App\Models\MedicalDocument;
use App\Models\Patient;
use App\Models\User;

/**
 * Who may download a prescription or report.
 *
 * The single most sensitive authorisation decision in the application: these
 * are health records, and getting this wrong hands one patient another
 * patient's diagnosis.
 */
class MedicalDocumentPolicy
{
    public function viewAny(User|Patient $actor): bool
    {
        return true;
    }

    /**
     * Two conditions for a patient, and BOTH must hold:
     *
     *   1. The document is theirs.
     *   2. The doctor has actually released it. A report uploaded but held
     *      back for review must stay invisible even to the patient it belongs
     *      to — that is the entire point of the flag.
     *
     * Staff see everything, released or not.
     */
    public function view(User|Patient $actor, MedicalDocument $document): bool
    {
        if ($actor instanceof User) {
            return true;
        }

        return $actor->getKey() === $document->patient_id
            && $document->is_visible_to_patient;
    }

    public function download(User|Patient $actor, MedicalDocument $document): bool
    {
        return $this->view($actor, $document);
    }

    /** Only staff issue documents. */
    public function create(User|Patient $actor): bool
    {
        return $actor instanceof User;
    }

    public function update(User|Patient $actor, MedicalDocument $document): bool
    {
        return $actor instanceof User;
    }

    public function delete(User|Patient $actor, MedicalDocument $document): bool
    {
        return $actor instanceof User;
    }
}
