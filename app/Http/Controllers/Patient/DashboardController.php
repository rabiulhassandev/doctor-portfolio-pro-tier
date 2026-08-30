<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What a patient sees when they sign in.
 *
 * Deliberately a summary rather than a menu: the next appointment, anything new
 * to download, and a way to book again. Everything else is one click away.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $patient = $request->user('patient');

        return view('pages.patient.dashboard', [
            'patient' => $patient,

            'upcoming' => $patient->appointments()
                ->where('starts_at', '>=', now())
                ->blocking()
                ->orderBy('starts_at')
                ->limit(5)
                ->get(),

            'recent' => $patient->appointments()
                ->where('starts_at', '<', now())
                ->orderByDesc('starts_at')
                ->limit(3)
                ->get(),

            // Only what the doctor has actually released — see
            // Patient::visibleDocuments().
            'documents' => $patient->visibleDocuments()
                ->latestFirst()
                ->limit(4)
                ->get(),

            'documentCount' => $patient->visibleDocuments()->count(),
        ]);
    }
}
