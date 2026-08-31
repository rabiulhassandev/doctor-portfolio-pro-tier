<?php

namespace App\Http\Controllers;

use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the managed redirects, from the route fallback.
 *
 * ---------------------------------------------------------------------------
 * WHY A FALLBACK ROUTE AND NOT MIDDLEWARE
 * ---------------------------------------------------------------------------
 *
 * Middleware is the obvious place for this and it is the wrong one. Global
 * middleware runs on EVERY request, so every page view on the site would carry
 * a redirects query — on shared hosting, for a table that is empty on most
 * installs and that can only ever match a URL which does not exist.
 *
 * `Route::fallback()` runs only when nothing else matched, which is precisely
 * the set of requests a redirect could apply to. Normal traffic costs nothing.
 *
 * The trade: a redirect cannot shadow a live route. That is a feature. A rule
 * quietly intercepting /services would be extremely hard to diagnose, and no
 * legitimate redirect needs to.
 */
class RedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $redirect = Redirect::match($request->path());

        if (! $redirect) {
            // Nothing matched, so this really is a 404. Throwing rather than
            // returning keeps Laravel's own error page and status handling.
            throw new NotFoundHttpException;
        }

        $redirect->recordHit();

        /*
         | The query string is carried over. Someone following a link with a
         | campaign tag on it should arrive with the tag intact, and dropping it
         | breaks the analytics of the very campaign that produced the click.
         */
        $target = $redirect->target();
        $query = $request->getQueryString();

        if ($query && ! str_contains($target, '?')) {
            $target .= '?'.$query;
        }

        return redirect()->away($target, $redirect->status_code);
    }
}
