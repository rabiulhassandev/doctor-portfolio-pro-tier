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

            'videos' => config('site.features.health_videos')
                ? HealthVideo::query()->published()->featured()->ordered()->limit(3)->get()
                : collect(),
        ]);
    }
}
