<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Search settings for one of the fixed pages, keyed by route name.
 *
 * @property string $route_name
 * @property string|null $title
 * @property string|null $description
 * @property string|null $share_image
 * @property string|null $canonical_url
 * @property bool $noindex
 * @property bool $nofollow
 * @property string|null $changefreq
 * @property float|null $priority
 * @property array<string, mixed>|null $custom_schema
 */
class SeoPage extends Model
{
    /** Container key under which the whole table is cached for the request. */
    private const CONTAINER_KEY = 'seo.pages.all';

    /**
     * The pages this feature manages, in the order the admin list shows them.
     *
     * A hard-coded manifest rather than a scan of the route table, for two
     * reasons. Laravel's route list also holds the patient account, the payment
     * callbacks, the sitemap and Filament's own hundred routes, none of which
     * has a search listing to tune. And the label and description below are
     * written for a doctor, not derived from a route name — "videos.index"
     * tells them nothing.
     *
     * `feature` ties a row to a config/site.php switch, so a practice with the
     * blog turned off is not offered SEO settings for a page that 404s.
     *
     * @var array<string, array{label: string, hint: string, feature?: string}>
     */
    public const MANAGED = [
        'home' => [
            'label' => 'Home page',
            'hint' => 'The page most people land on from a search for your name.',
        ],
        'about' => [
            'label' => 'About',
            'hint' => 'Your training and experience. Worth targeting your specialisation and city.',
        ],
        'services' => [
            'label' => 'Services',
            'hint' => 'What you treat. Usually the best page to aim at condition searches.',
        ],
        'booking' => [
            'label' => 'Book an appointment',
            'hint' => 'The page that turns a visitor into a patient.',
            'feature' => 'booking',
        ],
        'contact' => [
            'label' => 'Contact',
            'hint' => 'Address and hours. Carries your local-business markup.',
        ],
        'blog.index' => [
            'label' => 'Articles',
            'hint' => 'The index. Individual articles carry their own settings.',
            'feature' => 'blog',
        ],
        'videos.index' => [
            'label' => 'Health videos',
            'hint' => 'The library index. Individual videos carry their own settings.',
            'feature' => 'health_videos',
        ],
        'faq' => [
            'label' => 'Common questions',
            'hint' => 'Your best chance of being quoted directly by Google or an AI assistant.',
            'feature' => 'faq',
        ],
        'gallery' => [
            'label' => 'Gallery',
            'hint' => 'Photographs of the chamber.',
            'feature' => 'gallery',
        ],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'noindex' => 'boolean',
            'nofollow' => 'boolean',
            'priority' => 'float',
            'custom_schema' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetAll());
        static::deleted(fn () => static::forgetAll());
    }

    /**
     * Every row, keyed by route name and cached for the request.
     *
     * One query for the whole table. It has at most nine rows, so loading all
     * of it is cheaper than the `where route_name = ?` the layout would
     * otherwise run on each render — and the layout is not the only caller:
     * the sitemap wants the lot.
     *
     * NOT called `all()`. Eloquent already has a static `all()` with different
     * semantics and a `$columns` signature, and Filament calls it; overriding
     * it to return something keyed and cached is the kind of shadowing that
     * works until the day it does not.
     *
     * @return Collection<string, self>
     */
    public static function cached(): Collection
    {
        if (! app()->bound(self::CONTAINER_KEY)) {
            app()->scoped(
                self::CONTAINER_KEY,
                fn (): Collection => static::query()->get()->keyBy('route_name')
            );
        }

        return app(self::CONTAINER_KEY);
    }

    public static function forgetAll(): void
    {
        app()->forgetInstance(self::CONTAINER_KEY);
    }

    /** The settings for a route, or null where the doctor has not tuned it. */
    public static function forRoute(?string $routeName): ?self
    {
        return blank($routeName) ? null : static::cached()->get($routeName);
    }

    /** The settings for the request being handled, if any. */
    public static function forCurrentRequest(): ?self
    {
        return static::forRoute(request()->route()?->getName());
    }

    /**
     * The managed pages a buyer's feature switches leave switched on.
     *
     * @return array<string, array{label: string, hint: string, feature?: string}>
     */
    public static function availablePages(): array
    {
        return array_filter(
            self::MANAGED,
            fn (array $page): bool => ! isset($page['feature'])
                || (bool) config('site.features.'.$page['feature']),
        );
    }

    /**
     * Make sure there is a row for every managed page that is switched on.
     *
     * Called when the admin list opens. Without it the table starts empty and
     * the doctor is asked to "create" an SEO record for a page that plainly
     * already exists — which is a confusing thing to ask, and invites them to
     * type a route name by hand and get it wrong.
     *
     * Idempotent, and it never deletes: a row for a page whose feature was
     * later switched off keeps its tuned title, so switching the feature back
     * on restores the work rather than losing it.
     */
    public static function syncManagedPages(): void
    {
        foreach (array_keys(static::availablePages()) as $routeName) {
            static::query()->firstOrCreate(['route_name' => $routeName]);
        }

        static::forgetAll();
    }

    /** The human label for this row's route. */
    public function label(): string
    {
        return self::MANAGED[$this->route_name]['label'] ?? $this->route_name;
    }

    /**
     * The live URL, or null if the route has gone.
     *
     * A row can outlive its route — a buyer deletes a page, or a feature switch
     * is turned off — and route() would throw. The admin table shows a dash and
     * the sitemap skips it.
     */
    public function url(): ?string
    {
        return Route::has($this->route_name) ? route($this->route_name) : null;
    }
}
