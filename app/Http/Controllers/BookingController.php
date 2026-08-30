<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The booking page.
 *
 * All the behaviour lives in App\Livewire\BookingWizard — picking a date
 * reloads the times, which needs to happen without a page refresh. This
 * controller just renders the page it sits on.
 *
 * Turning `booking` off in config/site.php reverts the site to the Standard
 * tier's behaviour: no live slots, patients telephone instead.
 */
class BookingController extends Controller
{
    public function __invoke(): View
    {
        if (! config('site.features.booking')) {
            throw new NotFoundHttpException;
        }

        return view('pages.booking');
    }
}
