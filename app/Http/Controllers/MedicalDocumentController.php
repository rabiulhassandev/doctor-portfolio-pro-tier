<?php

namespace App\Http\Controllers;

use App\Models\MedicalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Handing a patient their prescription or report.
 *
 * ===========================================================================
 * THIS CONTROLLER IS THE ONLY WAY TO A MEDICAL FILE.
 * ===========================================================================
 *
 * Those files live on the private `medical` disk: outside public/, not reachable
 * through the storage symlink, and configured with `serve => false` so Laravel
 * registers no route to them. There is no URL to guess, and the model
 * deliberately has no url() accessor to add one by accident.
 *
 * Every request is authorised through MedicalDocumentPolicy before a single
 * byte is sent.
 */
class MedicalDocumentController extends Controller
{
    /** The patient's own list of documents. */
    public function index(Request $request): View
    {
        $patient = $request->user('patient');

        return view('pages.patient.documents', [
            'documents' => $patient->visibleDocuments()
                ->with('appointment')
                ->latestFirst()
                ->paginate(20),
        ]);
    }

    public function download(Request $request, MedicalDocument $document): StreamedResponse
    {
        /*
         | Works for both audiences: a signed-in patient (the `patient` guard)
         | and a member of staff (the `web` guard). Gate::allows() checks
         | whichever one is authenticated against MedicalDocumentPolicy, which
         | requires the document to belong to that patient AND to have been
         | released to them.
         */
        $actor = $request->user('patient') ?? $request->user();

        abort_unless($actor && Gate::forUser($actor)->allows('download', $document), 403);

        /*
         | A row can outlive its file — someone tidying storage, a failed
         | restore. 404 rather than a stack trace about a missing path.
         */
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        $document->recordDownload();

        return Storage::disk($document->disk)->download(
            $document->path,
            // Restore the human filename. On disk it is a ULID, which is what
            // stops a hostile upload name ever being used as a path.
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                /*
                 | nosniff is load-bearing. Laboratories email .html and .svg
                 | reports; without this header the browser would happily run
                 | one as a document on the clinic's own origin, which is
                 | stored XSS with a patient's session attached.
                 */
                'X-Content-Type-Options' => 'nosniff',
                // Health records must not sit in a shared proxy cache.
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }
}
