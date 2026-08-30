<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GalleryController extends Controller
{
    public function __invoke(): View
    {
        // Turning the feature off in config/site.php hides the page as well as
        // the navigation link, rather than leaving an orphan URL live.
        if (! config('site.features.gallery')) {
            throw new NotFoundHttpException;
        }

        return view('pages.gallery', [
            'images' => GalleryImage::query()->ordered()->get(),
        ]);
    }
}
