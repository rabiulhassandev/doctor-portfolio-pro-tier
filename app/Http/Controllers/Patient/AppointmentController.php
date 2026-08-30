<?php

namespace App\Http\Controllers\Patient;

use App\Enums\BookingActor;
use App\Exceptions\InvalidStatusTransition;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Booking\AppointmentWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A patient's own appointments.
 *
 * Every method authorises through AppointmentPolicy, which checks the
 * appointment actually belongs to the signed-in patient. Route-model binding
 * resolves on the booking reference, but a reference is still a value someone
 * could type — ownership is checked, never assumed.
 */
class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $patient = $request->user('patient');

        return view('pages.patient.appointments', [
            'upcoming' => $patient->appointments()
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->get(),

            'past' => $patient->appointments()
                ->where('starts_at', '<', now())
                ->orderByDesc('starts_at')
                ->paginate(10),
        ]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->authorize('view', $appointment);

        return view('pages.patient.appointment', [
            'appointment' => $appointment->load(['documents' => fn ($q) => $q->visibleToPatient()]),
            'workflow' => app(AppointmentWorkflow::class),
        ]);
    }

    /**
     * The patient calls off their own appointment.
     *
     * All the rules — is this a legal move, is there still enough notice, what
     * gets logged, who gets emailed — live in AppointmentWorkflow. This method
     * only decides what the patient then sees.
     */
    public function cancel(Request $request, Appointment $appointment, AppointmentWorkflow $workflow): RedirectResponse
    {
        $this->authorize('cancel', $appointment);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $workflow->cancel($appointment, BookingActor::Patient, $validated['reason'] ?? null);
        } catch (InvalidStatusTransition $e) {
            // Expected: a stale page, or the cutoff passing while they read it.
            // The message is already written for a patient.
            return back()->withErrors(['appointment' => $e->getMessage()]);
        }

        return redirect()
            ->route('patient.appointments.index')
            ->with('status', 'Your appointment has been cancelled. We have emailed you to confirm.');
    }
}
