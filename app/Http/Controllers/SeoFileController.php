<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\DoctorProfile;
use App\Models\Faq;
use App\Models\HealthVideo;
use App\Models\SeoPage;
use App\Models\SeoSetting;
use App\Models\Service;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The two plain-text files at the root of the site: robots.txt and llms.txt.
 *
 * Both are GENERATED, not stored. robots.txt used to be a static file in
 * public/, which meant the one instruction every crawler reads first could only
 * be changed by someone with FTP access — and the sitemap line in it pointed at
 * localhost until a developer remembered to fix it, which is the sort of thing
 * nobody remembers.
 *
 * >>> If you are restoring public/robots.txt, delete it again. <<<
 * A real file in public/ is served by the web server before Laravel ever sees
 * the request, so it silently wins over this route and every setting in the
 * admin panel stops having any effect.
 */
class SeoFileController extends Controller
{
    /**
     * /robots.txt
     *
     * Assembled in the order crawlers expect: the general rule first, then the
     * per-agent blocks, then the sitemap. Anything the doctor typed into the
     * "extra rules" box goes in before the sitemap line, so a bad paste cannot
     * push the sitemap out of the file.
     */
    public function robots(): Response
    {
        $settings = SeoSetting::current();
        $lines = [];

        if ($settings->discourage_indexing) {
            /*
             | The staging case. Nothing else in the file matters, and adding
             | the usual Allow rules underneath would only muddy it — several
             | crawlers resolve conflicting directives by longest match, so a
             | file that says both is a file that says nothing reliable.
             */
            $lines[] = '# This site has asked not to be indexed.';
            $lines[] = '# Turn off "Ask search engines not to index this site" in the admin panel.';
            $lines[] = '';
            $lines[] = 'User-agent: *';
            $lines[] = 'Disallow: /';

            return $this->text(implode("\n", $lines));
        }

        $lines[] = 'User-agent: *';
        $lines[] = 'Allow: /';
        $lines[] = '';
        $lines[] = '# The patient account area and the admin panel have nothing worth';
        $lines[] = '# indexing, and appointment pages carry a booking reference in the URL.';
        $lines[] = 'Disallow: /patient/';
        $lines[] = 'Disallow: /admin';
        $lines[] = 'Disallow: /payments/';

        // Search parameters produce endless near-duplicate URLs.
        $lines[] = 'Disallow: /*?search=';
        $lines[] = 'Disallow: /*?topic=';

        if ($blocked = $settings->blockedCrawlers()) {
            $lines[] = '';
            $lines[] = '# AI crawlers switched off in the admin panel.';

            foreach ($blocked as $agent) {
                $lines[] = '';
                $lines[] = 'User-agent: '.$agent;
                $lines[] = 'Disallow: /';
            }
        }

        if (filled($settings->robots_extra)) {
            $lines[] = '';
            $lines[] = '# Added from the admin panel.';
            $lines[] = trim($settings->robots_extra);
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return $this->text(implode("\n", $lines));
    }

    /**
     * /llms.txt
     *
     * A plain-Markdown summary of the site for AI assistants, proposed by
     * Jeremy Howard in 2024. It is a convention rather than a ratified
     * standard and the major assistants do not commit to reading it — see the
     * note on the column in the migration for the honest assessment.
     *
     * Generated from the doctor's own content when the admin field is empty,
     * which is the right default: a hand-written one goes stale the moment an
     * article is published, and this one cannot.
     */
    public function llms(): Response
    {
        $settings = SeoSetting::current();

        if (filled($settings->llms_txt)) {
            return $this->text($settings->llms_txt);
        }

        return $this->text($this->generateLlmsTxt());
    }

    /**
     * Build the summary from what the practice has actually published.
     *
     * The shape follows the convention: an H1 with the site's name, a blockquote
     * summarising it, then H2 sections of annotated links. An assistant reading
     * this should be able to answer "who is this, what do they treat, where are
     * they, how do I book" without fetching a single HTML page.
     */
    private function generateLlmsTxt(): string
    {
        $doctor = DoctorProfile::current();
        $settings = SeoSetting::current();

        $name = $doctor->name ?: config('site.name');
        $out = [];

        $out[] = '# '.$name;
        $out[] = '';

        $summary = collect([
            $doctor->specialization,
            $doctor->chamber_name,
            $doctor->fullAddress(),
        ])->filter()->implode(' · ');

        $out[] = '> '.$summary;
        $out[] = '';

        if (filled($doctor->short_bio)) {
            $out[] = $doctor->short_bio;
            $out[] = '';
        }

        // The facts an assistant is most often asked to produce.
        $facts = collect([
            $doctor->registration() ? 'Registration: '.$doctor->registration() : null,
            $doctor->years_of_experience ? 'Years in practice: '.$doctor->years_of_experience : null,
            $doctor->phone ? 'Telephone: '.$doctor->phone : null,
            $doctor->email ? 'Email: '.$doctor->email : null,
            $settings->languages ? 'Languages: '.implode(', ', $settings->languages) : null,
            $doctor->hasFee()
                ? 'Consultation fee: '.config('booking.payment.currency', 'BDT').' '.number_format((float) $doctor->consultation_fee, 0)
                : null,
        ])->filter();

        if ($facts->isNotEmpty()) {
            $out[] = '## Practice details';
            $out[] = '';
            $facts->each(function (string $fact) use (&$out): void {
                $out[] = '- '.$fact;
            });
            $out[] = '';
        }

        // Opening hours, written out rather than left as a table to parse.
        $hours = $doctor->scheduleRows()
            ->map(fn (array $row): string => '- '.$row['label'].': '.(
                $row['is_closed'] || blank($row['opens'])
                    ? 'Closed'
                    : substr((string) $row['opens'], 0, 5).'–'.substr((string) $row['closes'], 0, 5)
            ));

        if ($hours->isNotEmpty()) {
            $out[] = '## Opening hours';
            $out[] = '';
            $out = array_merge($out, $hours->all());
            $out[] = '';
        }

        $services = Service::query()->published()->ordered()->get();

        if ($services->isNotEmpty()) {
            $out[] = '## Services';
            $out[] = '';
            $services->each(function (Service $service) use (&$out): void {
                $summary = $service->shortSummary(160);
                $out[] = '- **'.$service->title.'**'.($summary ? ': '.$summary : '');
            });
            $out[] = '';
        }

        $out[] = '## Key pages';
        $out[] = '';

        foreach (SeoPage::availablePages() as $routeName => $page) {
            if (! Route::has($routeName)) {
                continue;
            }

            /*
             | The doctor's own description, or nothing.
             |
             | NOT the `hint` from SeoPage::MANAGED — that is coaching written
             | for whoever is filling the form in ("Worth targeting your
             | specialisation and city"), and publishing it to an AI crawler
             | would be describing the practice in the second person and
             | handing out its SEO strategy at the same time.
             */
            $description = SeoPage::forRoute($routeName)?->description;

            $out[] = '- ['.$page['label'].']('.route($routeName).')'
                .(filled($description) ? ': '.$description : '');
        }

        $out[] = '';

        if (config('site.features.faq')) {
            $faqs = Faq::query()->published()->ordered()->get();

            if ($faqs->isNotEmpty()) {
                $out[] = '## Common questions';
                $out[] = '';
                $faqs->each(function (Faq $faq) use (&$out): void {
                    $out[] = '### '.$faq->question;
                    $out[] = '';
                    $out[] = Str::limit(strip_tags($faq->answer), 400, preserveWords: true);
                    $out[] = '';
                });
            }
        }

        if (config('site.features.blog')) {
            $posts = BlogPost::query()->published()->latestFirst()->limit(20)->get();

            if ($posts->isNotEmpty()) {
                $out[] = '## Articles';
                $out[] = '';
                $posts->each(function (BlogPost $post) use (&$out): void {
                    $out[] = '- ['.$post->title.']('.route('blog.show', $post->slug).'): '.$post->summary(160);
                });
                $out[] = '';
            }
        }

        if (config('site.features.health_videos')) {
            $videos = HealthVideo::query()->published()->ordered()->limit(20)->get();

            if ($videos->isNotEmpty()) {
                $out[] = '## Patient education videos';
                $out[] = '';
                $videos->each(function (HealthVideo $video) use (&$out): void {
                    $out[] = '- ['.$video->title.']('.route('videos.show', $video->slug).'): '.$video->summary(160);
                });
                $out[] = '';
            }
        }

        $out[] = '---';
        $out[] = '';
        $out[] = 'This page is general information about a medical practice. It is not medical advice.';

        return implode("\n", $out);
    }

    /**
     * `text/plain; charset=utf-8` — the charset is not optional here.
     *
     * Both files can contain Bangla (a chamber name, a service title), and a
     * plain-text response without a declared charset is decoded as Latin-1 by
     * several crawlers, which turns every Bengali character into mojibake.
     */
    private function text(string $body): Response
    {
        return response($body, 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
