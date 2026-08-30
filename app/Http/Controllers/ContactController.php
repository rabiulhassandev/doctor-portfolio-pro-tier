<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Address, map, hours and the ways to get in touch.
 *
 * Unlike the Standard tier there is no enquiry form here: this tier has real
 * booking, and a second "request an appointment" form beside it would only
 * split the practice's inbox between two systems.
 */
class ContactController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.contact');
    }
}
