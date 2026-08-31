<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\DoctorProfile;
use App\Models\Faq;
use App\Models\GalleryImage;
use App\Models\HealthVideo;
use App\Models\SeoPage;
use App\Models\SeoSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Checks the site's actual content and reports what is wrong with it.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS
 * ---------------------------------------------------------------------------
 *
 * Every other screen in this feature lets a doctor CHANGE something. None of
 * them tells them what is worth changing, and a settings panel that assumes you
 * already know what to fill in is a settings panel for somebody who did not
 * need it.
 *
 * So this is the answer to "I have opened the SEO section, now what". It reads
 * the real records, produces a short ranked list of specific problems, and
 * gives each one a link to the exact screen that fixes it.
 *
 * Two rules kept it honest:
 *
 *   * NO SCORE. A number out of a hundred invites people to optimise the
 *     number, and every SEO plugin that has one ends up rewarding keyword
 *     stuffing because that is what it can measure. A list of real problems
 *     that empties out is more useful and cannot be gamed.
 *   * Only things that are actually wrong. A check that fires on a healthy
 *     site trains people to ignore the screen, and then it catches nothing.
 */
class SeoAudit
{
    public const CRITICAL = 'critical';

    public const WARNING = 'warning';

    public const SUGGESTION = 'suggestion';

    /**
     * Every finding, worst first.
     *
     * @return Collection<int, array{severity: string, title: string, detail: string, action: ?string, url: ?string}>
     */
    public static function run(): Collection
    {
        $findings = collect()
            ->merge(static::checkIndexing())
            ->merge(static::checkSettings())
            ->merge(static::checkPages())
            ->merge(static::checkContent())
            ->merge(static::checkLocalPresence())
            ->merge(static::checkOpportunities());

        $order = [self::CRITICAL => 0, self::WARNING => 1, self::SUGGESTION => 2];

        return $findings
            ->sortBy(fn (array $finding): int => $order[$finding['severity']] ?? 9)
            ->values();
    }

    /** @return array<int, array<string, mixed>> */
    public static function summary(): array
    {
        $findings = static::run();

        return [
            'critical' => $findings->where('severity', self::CRITICAL)->count(),
            'warning' => $findings->where('severity', self::WARNING)->count(),
            'suggestion' => $findings->where('severity', self::SUGGESTION)->count(),
            'total' => $findings->count(),
        ];
    }

    // -----------------------------------------------------------------------
    // Checks
    // -----------------------------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private static function checkIndexing(): array
    {
        $settings = SeoSetting::current();
        $findings = [];

        /*
         | The one finding that outranks everything else. A site with this
         | switch left on has no search presence at all, and nothing else on
         | this list matters until it is off.
         */
        if ($settings->discourage_indexing) {
            $findings[] = static::finding(
                self::CRITICAL,
                'Your website is hidden from every search engine',
                'The “ask search engines not to index this site” switch is on. Nobody can find you on Google until it is turned off. This is normal while a site is being built — it is a serious problem once it is live.',
                'Turn it off',
                '/admin/seo-settings?tab=-indexing-tab',
            );
        }

        // A canonical pointing at another domain removes the page from Google.
        $offSite = SeoPage::query()
            ->whereNotNull('canonical_url')
            ->get()
            ->reject(fn (SeoPage $page): bool => Str::startsWith($page->canonical_url, url('/')));

        foreach ($offSite as $page) {
            $findings[] = static::finding(
                self::CRITICAL,
                'The '.Str::lower($page->label()).' page points at another website',
                'Its canonical URL is set to '.$page->canonical_url.', which tells Google the real version of this page is somewhere else. Google will drop your page from its results. Clear the field unless this is genuinely what you meant.',
                'Fix it',
                '/admin/seo-pages/'.$page->getKey().'/edit',
            );
        }

        return $findings;
    }

    /** @return array<int, array<string, mixed>> */
    private static function checkSettings(): array
    {
        $settings = SeoSetting::current();
        $findings = [];

        if (blank($settings->default_meta_description)) {
            $findings[] = static::finding(
                self::WARNING,
                'No site-wide description',
                'Pages without a description of their own have nothing to fall back on, so Google invents one from whatever text it finds first. Write one sentence describing the practice.',
                'Write one',
                '/admin/seo-settings',
            );
        }

        if (blank($settings->google_verification)) {
            $findings[] = static::finding(
                self::SUGGESTION,
                'Google Search Console is not connected',
                'Search Console is free, and it is the only place that tells you what people searched for before they found you, and which of your pages Google is having trouble with. Add the site, paste the verification code here, then submit your sitemap.',
                'Add the code',
                '/admin/seo-settings?tab=-verification-tab',
            );
        }

        if (blank($settings->default_share_image)) {
            $findings[] = static::finding(
                self::SUGGESTION,
                'No image for shared links',
                'When somebody sends a link to your site on WhatsApp or Facebook, there is no picture with it. A link with a photograph gets noticeably more clicks than a bare one.',
                'Add an image',
                '/admin/seo-settings',
            );
        }

        /*
         | Search crawlers being blocked is worth flagging loudly. The training
         | ones are a values decision and are deliberately NOT reported — this
         | screen has no business nagging somebody about a choice they made on
         | purpose.
         */
        $blockedSearch = collect(SeoSetting::AI_CRAWLERS)
            ->filter(fn (array $bot, string $agent): bool => $bot['kind'] === 'search'
                && ! $settings->allowsCrawler($agent));

        if ($blockedSearch->isNotEmpty()) {
            $findings[] = static::finding(
                self::WARNING,
                'You are blocked from AI search results',
                'These crawlers fetch pages so an assistant can quote you and link back: '
                    .$blockedSearch->pluck('label')->implode(', ')
                    .'. Blocking them means people asking ChatGPT or Perplexity for a doctor in your area will not be shown you. This is separate from model training, which stays off if you have turned it off.',
                'Review',
                '/admin/seo-settings?tab=-ai-assistants-tab',
            );
        }

        return $findings;
    }

    /** @return array<int, array<string, mixed>> */
    private static function checkPages(): array
    {
        $findings = [];
        $pages = SeoPage::query()->get();

        $missing = $pages->filter(fn (SeoPage $page): bool => blank($page->description));

        if ($missing->isNotEmpty()) {
            $findings[] = static::finding(
                self::WARNING,
                $missing->count().' '.Str::plural('page', $missing->count()).' without a description',
                'These pages have no description of their own: '
                    .$missing->map(fn (SeoPage $p): string => $p->label())->implode(', ')
                    .'. Each one is a chance to say something specific about that page in search results.',
                'Write them',
                '/admin/seo-pages',
            );
        }

        foreach ($pages as $page) {
            if (filled($page->title) && mb_strlen($page->title) > 60) {
                $findings[] = static::finding(
                    self::SUGGESTION,
                    'The '.Str::lower($page->label()).' title is too long',
                    'It is '.mb_strlen($page->title).' characters. Google cuts titles off at about 60, so the end of it will not be seen.',
                    'Shorten it',
                    '/admin/seo-pages/'.$page->getKey().'/edit',
                );
            }
        }

        return $findings;
    }

    /** @return array<int, array<string, mixed>> */
    private static function checkContent(): array
    {
        $findings = [];

        if (config('site.features.blog')) {
            $postsWithoutMeta = BlogPost::query()
                ->published()
                ->whereNull('meta_description')
                ->whereNull('excerpt')
                ->count();

            if ($postsWithoutMeta > 0) {
                $findings[] = static::finding(
                    self::WARNING,
                    $postsWithoutMeta.' '.Str::plural('article', $postsWithoutMeta).' without a summary',
                    'Google is taking the first two lines of the article instead, which usually reads like the middle of a sentence. Add a summary on each — one or two lines describing what the reader will learn.',
                    'Open articles',
                    '/admin/blog-posts',
                );
            }

            $withoutCover = BlogPost::query()->published()->whereNull('cover_image')->count();

            if ($withoutCover > 0) {
                $findings[] = static::finding(
                    self::SUGGESTION,
                    $withoutCover.' '.Str::plural('article', $withoutCover).' without a cover image',
                    'Articles with a picture are more likely to be shown with a thumbnail in search results and look far better when shared.',
                    'Open articles',
                    '/admin/blog-posts',
                );
            }
        }

        if (config('site.features.health_videos')) {
            $videosWithoutDescription = HealthVideo::query()
                ->published()
                ->where(fn ($query) => $query->whereNull('description')->orWhere('description', ''))
                ->count();

            if ($videosWithoutDescription > 0) {
                $findings[] = static::finding(
                    self::WARNING,
                    $videosWithoutDescription.' '.Str::plural('video', $videosWithoutDescription).' without a description',
                    'A video page with no text on it has almost nothing for a search engine to read. The description is what gets the video found.',
                    'Open videos',
                    '/admin/health-videos',
                );
            }
        }

        if (config('site.features.gallery')) {
            $withoutAlt = GalleryImage::query()
                ->where(fn ($query) => $query->whereNull('alt_text')->orWhere('alt_text', ''))
                ->count();

            if ($withoutAlt > 0) {
                $findings[] = static::finding(
                    self::SUGGESTION,
                    $withoutAlt.' '.Str::plural('photograph', $withoutAlt).' without a description',
                    'Alt text describes a picture to somebody using a screen reader, and to Google Images. It is an accessibility requirement first and a search benefit second.',
                    'Open gallery',
                    '/admin/gallery-images',
                );
            }
        }

        return $findings;
    }

    /**
     * Local search, which for a single chamber is most of the game.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function checkLocalPresence(): array
    {
        $doctor = DoctorProfile::current();
        $findings = [];

        $missing = collect([
            'a street address' => blank($doctor->address_line),
            'a city' => blank($doctor->city),
            'a telephone number' => blank($doctor->phone),
        ])->filter()->keys();

        if ($missing->isNotEmpty()) {
            $findings[] = static::finding(
                self::WARNING,
                'Your practice details are incomplete',
                'Missing: '.$missing->implode(', ').'. These are published as structured data and are what puts a practice into Google’s local results with a map pin — the panel that appears for “cardiologist near me”.',
                'Complete them',
                '/admin/doctor-profile-settings',
            );
        }

        if (blank($doctor->map_latitude) || blank($doctor->map_longitude)) {
            $findings[] = static::finding(
                self::SUGGESTION,
                'No map location set',
                'Coordinates let search engines place the chamber precisely rather than guessing from the address.',
                'Set the location',
                '/admin/doctor-profile-settings',
            );
        }

        $hasOpenDay = collect($doctor->working_hours ?? [])
            ->contains(fn (array $row): bool => ! ($row['is_closed'] ?? false) && filled($row['opens'] ?? null));

        if (! $hasOpenDay) {
            $findings[] = static::finding(
                self::WARNING,
                'No opening hours',
                'Google shows “Open now” or “Closed” beside a practice in local results, and cannot without hours. It is also the first thing a patient looks for.',
                'Add hours',
                '/admin/doctor-profile-settings',
            );
        }

        return $findings;
    }

    /**
     * Things that are not wrong, but are being left on the table.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function checkOpportunities(): array
    {
        $findings = [];

        if (config('site.features.faq')) {
            $faqCount = Faq::query()->published()->count();

            if ($faqCount < 5) {
                $findings[] = static::finding(
                    self::SUGGESTION,
                    'Only '.$faqCount.' published '.Str::plural('question', $faqCount),
                    'Questions and answers are the single most quotable thing on a medical site. Google shows them directly in results, and an AI assistant asked “does this doctor take walk-ins” will read exactly this. Write down what your staff answer on the telephone all day.',
                    'Add questions',
                    '/admin/faqs',
                );
            }
        }

        if (config('site.features.blog')) {
            $published = BlogPost::query()->published()->count();

            if ($published < 3) {
                $findings[] = static::finding(
                    self::SUGGESTION,
                    'Very little written content',
                    'A site with '.$published.' '.Str::plural('article', $published).' has few ways to be found for anything other than your name. Articles answering the questions patients actually arrive with are what bring in people who have not heard of you.',
                    'Write an article',
                    '/admin/blog-posts',
                );
            }
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    private static function finding(
        string $severity,
        string $title,
        string $detail,
        ?string $action = null,
        ?string $url = null,
    ): array {
        return compact('severity', 'title', 'detail', 'action', 'url');
    }
}
