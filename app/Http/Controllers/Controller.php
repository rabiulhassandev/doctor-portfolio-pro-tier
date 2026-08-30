<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * The base controller.
 *
 * Laravel 11 removed AuthorizesRequests from the skeleton's base controller.
 * It is added back here because this application genuinely needs it: patients
 * and staff share several routes, and `$this->authorize()` against
 * AppointmentPolicy / MedicalDocumentPolicy is what keeps one patient out of
 * another's records.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
