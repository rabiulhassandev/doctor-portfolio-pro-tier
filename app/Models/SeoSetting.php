<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Site-wide search settings. One row, ever.
 *
 * Read it with {@see SeoSetting::current()}, which caches the instance for the
 * lifetime of the request — the layout asks it for the title template, the
 * robots directive, the verification tags and the analytics IDs on every page
 * render, and none of that should cost four queries.
 *
 * On a brand-new install there is no row. `current()` returns an unsaved model
 * carrying the column defaults rather than null, so nothing downstream has to
 * null-check; every accessor below already falls back sensibly.
 *
 * @property string $title_template
 * @property string|null $default_meta_description
 * @property string|null $default_share_image
 * @property string|null $twitter_handle
 * @property bool $discourage_indexing
 * @property string|null $robots_extra
 * @property array<string, bool>|null $ai_crawlers
 * @property string|null $llms_txt
 * @property string|null $google_verification
 * @property string|null $bing_verification
 * @property string|null $yandex_verification
 * @property string|null $pinterest_verification
 * @property string|null $ga4_measurement_id
 * @property string|null $gtm_container_id
 * @property string|null $meta_pixel_id
 * @property string|null $head_scripts
 * @property string|null $body_scripts
 * @property string|null $legal_name
 * @property string|null $founding_year
 * @property string|null $price_range
 * @property array<int, string>|null $areas_served
 * @property array<int, string>|null $languages
 * @property array<int, string>|null $payment_accepted
 * @property array<string, mixed>|null $custom_schema
 */
class SeoSetting extends Model
{
    /** Container key under which the row is cached for the request. */
    private const CONTAINER_KEY = 'seo.settings.current';

    /**
     * The AI crawlers the panel offers a switch for.
     *
     * Grouped by what they actually DO, because the two groups deserve
     * different answers and lumping them together is how a practice
     * accidentally makes itself uncitable:
     *
     *   search   — fetches a page in order to cite it in an answer. Blocking
     *              these is the modern equivalent of blocking Googlebot, and
     *              on a site whose whole purpose is being found it is almost
     *              always a mistake.
     *   training — collects text to train a model. Whether to allow it is a
     *              values question, and the answer is legitimately "no" for
     *              plenty of people.
     *
     * The user-agent strings are what each vendor documents. They change; the
     * admin form links to the source and this array is the one place to edit.
     *
     * @var array<string, array{label: string, vendor: string, kind: string}>
     */
    public const AI_CRAWLERS = [
        'OAI-SearchBot' => ['label' => 'ChatGPT search', 'vendor' => 'OpenAI', 'kind' => 'search'],
        'ChatGPT-User' => ['label' => 'ChatGPT browsing', 'vendor' => 'OpenAI', 'kind' => 'search'],
        'GPTBot' => ['label' => 'OpenAI model training', 'vendor' => 'OpenAI', 'kind' => 'training'],
        'Claude-SearchBot' => ['label' => 'Claude search', 'vendor' => 'Anthropic', 'kind' => 'search'],
        'Claude-User' => ['label' => 'Claude browsing', 'vendor' => 'Anthropic', 'kind' => 'search'],
        'ClaudeBot' => ['label' => 'Anthropic model training', 'vendor' => 'Anthropic', 'kind' => 'training'],
        'PerplexityBot' => ['label' => 'Perplexity search', 'vendor' => 'Perplexity', 'kind' => 'search'],
        'Google-Extended' => ['label' => 'Gemini model training', 'vendor' => 'Google', 'kind' => 'training'],
        'Applebot-Extended' => ['label' => 'Apple model training', 'vendor' => 'Apple', 'kind' => 'training'],
        'meta-externalagent' => ['label' => 'Meta AI', 'vendor' => 'Meta', 'kind' => 'training'],
        'CCBot' => ['label' => 'Common Crawl', 'vendor' => 'Common Crawl', 'kind' => 'training'],
        'Bytespider' => ['label' => 'ByteDance', 'vendor' => 'ByteDance', 'kind' => 'training'],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'discourage_indexing' => 'boolean',
            'ai_crawlers' => 'array',
            'areas_served' => 'array',
            'languages' => 'array',
            'payment_accepted' => 'array',
            'custom_schema' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Any save invalidates the request cache, so an admin who saves and
        // then opens the site in the next tab sees the change.
        static::saved(fn () => static::forgetCurrent());
        static::deleted(fn () => static::forgetCurrent());
    }

    /**
     * The one row, cached per request.
     *
     * Returns an unsaved instance holding the column defaults when the table is
     * empty, so callers never have to null-check.
     */
    public static function current(): self
    {
        if (! app()->bound(self::CONTAINER_KEY)) {
            app()->scoped(
                self::CONTAINER_KEY,
                fn (): self => static::query()->first() ?? new static([
                    'title_template' => ':page | :site',
                ])
            );
        }

        return app(self::CONTAINER_KEY);
    }

    public static function forgetCurrent(): void
    {
        app()->forgetInstance(self::CONTAINER_KEY);
    }

    /**
     * Assemble a page <title> from the template.
     *
     * The home page passes null and gets the site name alone — "Dr. Tahmina
     * Rahman | Dr. Tahmina Rahman" is the classic template giveaway.
     */
    public function buildTitle(?string $pageTitle, string $siteName): string
    {
        if (blank($pageTitle) || trim($pageTitle) === trim($siteName)) {
            return $siteName;
        }

        return trim(str_replace(
            [':page', ':site'],
            [trim($pageTitle), trim($siteName)],
            $this->title_template ?: ':page | :site',
        ));
    }

    /**
     * Whether a crawler is allowed.
     *
     * Unknown and unset agents default to ALLOWED. A doctor who has never
     * opened this screen should be findable, and silently blocking a crawler
     * nobody chose to block is the worse failure by a distance.
     */
    public function allowsCrawler(string $userAgent): bool
    {
        return (bool) ($this->ai_crawlers[$userAgent] ?? true);
    }

    /** The AI crawlers currently switched off, in robots.txt order. */
    public function blockedCrawlers(): array
    {
        return collect(self::AI_CRAWLERS)
            ->keys()
            ->reject(fn (string $agent): bool => $this->allowsCrawler($agent))
            ->values()
            ->all();
    }

    /**
     * The value for <meta name="robots">, or null to emit nothing.
     *
     * `noindex, nofollow` while the staging switch is on. Otherwise the
     * `max-image-preview:large` family, which is what allows a full-size
     * thumbnail beside a result and an unclipped snippet — measurably the
     * cheapest win available on a site with photographs and long answers.
     */
    public function robotsDirective(bool $pageNoindex = false, bool $pageNofollow = false): string
    {
        if ($this->discourage_indexing) {
            return 'noindex, nofollow';
        }

        return implode(', ', array_filter([
            $pageNoindex ? 'noindex' : 'index',
            $pageNofollow ? 'nofollow' : 'follow',
            $pageNoindex ? null : 'max-image-preview:large',
            $pageNoindex ? null : 'max-snippet:-1',
            $pageNoindex ? null : 'max-video-preview:-1',
        ]));
    }

    /** The @handle, normalised, for the twitter: card tags. */
    public function twitterHandle(): ?string
    {
        return blank($this->twitter_handle)
            ? null
            : '@'.ltrim(trim($this->twitter_handle), '@');
    }

    /**
     * Whether any third-party tracking is configured at all.
     *
     * The layout asks this before it renders a consent-relevant block, so a
     * practice that has filled nothing in ships a site that talks to nobody.
     */
    public function hasAnalytics(): bool
    {
        return filled($this->ga4_measurement_id)
            || filled($this->gtm_container_id)
            || filled($this->meta_pixel_id);
    }

    /** Google's own format check, so a mistyped ID is caught at save time. */
    public function ga4IsWellFormed(): bool
    {
        return blank($this->ga4_measurement_id)
            || Str::isMatch('/^G-[A-Z0-9]{6,}$/', $this->ga4_measurement_id);
    }
}
