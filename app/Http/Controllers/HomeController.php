<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\HealthVideo;
use App\Models\Service;
use App\Models\Testimonial;
use Illuminate\View\View;

/**
 * The home page.
 *
 * Everything is limited: the home page's job is to give a visitor enough to
 * decide to book, not to show them the whole site. Each section links onward
 * to the page that holds the rest.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.home', [
            'services' => Service::query()->published()->featured()->ordered()->limit(6)->get(),

            'testimonials' => config('site.features.testimonials')
                ? Testimonial::query()->published()->ordered()->limit(6)->get()
                : collect(),

            'posts' => config('site.features.blog')
                ? BlogPost::query()->published()->latestFirst()->limit(3)->get()
                : collect(),

            /*
             | Featured first, then filled out with whatever else is published.
             |
             | The section renders as one large film with the rest listed beside
             | it, and that column wants four entries to balance the feature. A
             | strict `featured()` filter gave it however many the doctor had
             | ticked — three on the seeded demo — and left a third of the
             | section empty. Curation still decides the order, and the lead
             | film in particular; it just no longer decides the height.
             */
            'videos' => config('site.features.health_videos')
                ? HealthVideo::query()->published()->orderByDesc('is_featured')->ordered()->limit(5)->get()
                : collect(),
        ]);
    }
}
