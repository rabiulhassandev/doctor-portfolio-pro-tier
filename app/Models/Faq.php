<?php

namespace App\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A question the chamber answers on the phone forty times a week.
 *
 * @property string $question
 * @property string $answer
 * @property string|null $category
 * @property bool $is_published
 * @property int $sort_order
 */
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    /** Laravel would otherwise pluralise this to "faqs" as "faqs" — it does not. */
    protected $table = 'faqs';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Published questions grouped under their category heading.
     *
     * Questions with no category are collected under a general heading rather
     * than dropped, so a doctor who never touches the category field still
     * gets a sensible page.
     *
     * @return Collection<string, Collection<int, self>>
     */
    public static function grouped(): Collection
    {
        return static::query()
            ->published()
            ->ordered()
            ->get()
            ->groupBy(fn (self $faq): string => $faq->category ?: 'General questions');
    }
}
