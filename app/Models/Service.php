<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A treatment or procedure the practice offers.
 *
 * @property string $title
 * @property string $slug
 * @property string|null $icon
 * @property string|null $summary
 * @property string|null $description
 * @property bool $is_featured
 * @property bool $is_published
 * @property int $sort_order
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Fill in the slug when one was not supplied.
     *
     * Pure normalisation with no side effect, which is what a model hook is
     * for. It means a seeder or a test can create a service with just a title.
     */
    protected static function booted(): void
    {
        static::saving(function (self $service): void {
            if (blank($service->slug)) {
                $service->slug = Str::slug($service->title);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The visibility rule, in one place.
     *
     * Always reach for this rather than checking the column by hand — that is
     * how a draft ends up visible on one page and hidden on another.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function scopeFeatured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /** The order the doctor arranged by dragging rows in the admin table. */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('title');
    }

    /** Falls back to the opening words of the description. */
    public function shortSummary(int $limit = 140): ?string
    {
        return $this->summary ?: Str::limit(strip_tags((string) $this->description), $limit, preserveWords: true);
    }
}
