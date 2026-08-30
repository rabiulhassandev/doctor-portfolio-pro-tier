<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FaqController extends Controller
{
    public function __invoke(): View
    {
        if (! config('site.features.faq')) {
            throw new NotFoundHttpException;
        }

        return view('pages.faq', [
            // Grouped by category, with uncategorised questions collected under
            // a general heading. See Faq::grouped().
            'groups' => Faq::grouped(),
        ]);
    }
}
