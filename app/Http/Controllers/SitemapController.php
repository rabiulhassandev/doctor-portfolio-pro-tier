<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\HealthVideo;
use App\Models\SeoPage;
use App\Models\SeoSetting;
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
        /*
         | The whole file is suppressed while the site is marked "do not index".
         | Serving a sitemap that advertises pages the robots.txt has just told
         | crawlers to stay away from is a contradiction, and Search Console
         | reports it as one.
         */
        if (SeoSetting::current()->discourage_indexing) {
            return response()
                ->view('sitemap', ['urls' => collect()])
                ->header('Content-Type', 'application/xml');
        }

        $urls = collect([
            $this->fixedPage('home', '1.0', 'weekly'),
            $this->fixedPage('about', '0.8', 'monthly'),
            $this->fixedPage('services', '0.8', 'monthly'),
            $this->fixedPage('contact', '0.9', 'monthly'),
        ]);

        if (config('site.features.booking')) {
            // The most valuable page on the site — it is the one that turns a
            // visitor into a patient.
            $urls->push($this->fixedPage('booking', '0.9', 'weekly'));
        }

        if (config('site.features.faq')) {
            $urls->push($this->fixedPage('faq', '0.7', 'monthly'));
        }

        if (config('site.features.gallery')) {
            $urls->push($this->fixedPage('gallery', '0.6', 'monthly'));
        }

        if (config('site.features.blog')) {
            $urls->push($this->fixedPage('blog.index', '0.8', 'weekly'));

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
            $urls->push($this->fixedPage('videos.index', '0.8', 'weekly'));

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
            // A page the doctor has marked "hide from search engines" must not
            // be advertised here either. `filter` rather than an `if` at each
            // call site, so a new page cannot forget to check.
            ->view('sitemap', ['urls' => $urls->filter()->values()])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * One of the fixed pages, with whatever the admin has overridden.
     *
     * Returns null when the page is marked noindex, which the caller filters
     * out. The defaults passed in are the developer's judgement about the
     * shape of a doctor's site; the admin's values win where they exist.
     *
     * @return array<string, string>|null
     */
    private function fixedPage(string $routeName, string $priority, string $changefreq): ?array
    {
        $override = SeoPage::forRoute($routeName);

        if ($override?->noindex) {
            return null;
        }

        return [
            'loc' => route($routeName),
            'priority' => $override?->priority !== null
                ? number_format($override->priority, 1)
                : $priority,
            'changefreq' => $override?->changefreq ?: $changefreq,
        ];
    }
}
