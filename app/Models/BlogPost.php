<?php

namespace App\Models;

use App\Support\Media;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An article the doctor wrote for patients.
 *
 * @property string $title
 * @property string $slug
 * @property string|null $cover_image
 * @property string|null $excerpt
 * @property string $content
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property string|null $meta_title
 * @property string|null $meta_description
 */
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $post): void {
            if (blank($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Live articles only.
     *
     * An article is live when the toggle is on AND its publish date has passed.
     * Both halves matter: the toggle is the draft switch, the date is what lets
     * the doctor line up next Tuesday's article today. This scope is the single
     * place that rule exists — never check the two columns by hand.
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('published_at');
    }

    public function coverUrl(): ?string
    {
        return Media::url($this->cover_image);
    }

    /** Falls back to the opening words of the article. */
    public function summary(int $limit = 160): string
    {
        // preserveWords: a summary cut through the middle of a word looks like
        // a bug wherever it appears, and this one also feeds meta descriptions.
        return $this->excerpt ?: Str::limit(strip_tags($this->content), $limit, preserveWords: true);
    }

    /** Rough reading time, rounded up, never less than a minute. */
    public function readingMinutes(): int
    {
        $words = str_word_count(strip_tags($this->content));

        return max(1, (int) ceil($words / 200));
    }
}
