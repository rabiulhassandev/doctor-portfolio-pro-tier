<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\HealthVideo;
use Illuminate\Http\Response;

/**
 * Generates /sitemap.xml on the fly.
 *
 * Building it per request (rather than writing a file to disk) means the doctor
 * publishes an article or a video in the admin panel and it is in the sitemap
 * immediately — no command to run, no cron job to set up. A single-doctor site
 * has a handful of pages, so the two queries behind this cost less than reading
 * a cached file would.
 *
 * Pages hidden by a feature switch in config/site.php are left out, so the
 * sitemap never advertises a URL that returns 404.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('about'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('services'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('contact'), 'priority' => '0.9', 'changefreq' => 'monthly'],
        ]);

        if (config('site.features.booking')) {
            // The most valuable page on the site — it is the one that turns a
            // visitor into a patient.
            $urls->push(['loc' => route('booking'), 'priority' => '0.9', 'changefreq' => 'weekly']);
        }

        if (config('site.features.faq')) {
            $urls->push(['loc' => route('faq'), 'priority' => '0.7', 'changefreq' => 'monthly']);
        }

        if (config('site.features.gallery')) {
            $urls->push(['loc' => route('gallery'), 'priority' => '0.6', 'changefreq' => 'monthly']);
        }

        if (config('site.features.blog')) {
            $urls->push(['loc' => route('blog.index'), 'priority' => '0.8', 'changefreq' => 'weekly']);

            BlogPost::query()
                ->published()
                ->latestFirst()
                ->get(['slug', 'updated_at', 'published_at'])
                ->each(function (BlogPost $post) use ($urls): void {
                    $urls->push([
                        'loc' => route('blog.show', $post->slug),
                        'lastmod' => ($post->updated_at ?? $post->published_at)?->toAtomString(),
                        'priority' => '0.7',
                        'changefreq' => 'monthly',
                    ]);
                });
        }

        if (config('site.features.health_videos')) {
            $urls->push(['loc' => route('videos.index'), 'priority' => '0.8', 'changefreq' => 'weekly']);

            HealthVideo::query()
                ->published()
                ->ordered()
                ->get(['slug', 'updated_at', 'published_at'])
                ->each(function (HealthVideo $video) use ($urls): void {
                    $urls->push([
                        'loc' => route('videos.show', $video->slug),
                        'lastmod' => ($video->updated_at ?? $video->published_at)?->toAtomString(),
                        'priority' => '0.7',
                        'changefreq' => 'monthly',
                    ]);
                });
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
