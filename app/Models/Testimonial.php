<?php

namespace App\Models;

use App\Support\Media;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Something a patient said about the practice.
 *
 * @property string $name
 * @property string|null $role
 * @property string|null $photo
 * @property string $message
 * @property int $rating
 * @property bool $is_published
 * @property int $sort_order
 */
class Testimonial extends Model
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
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
        $query->orderBy('sort_order')->orderByDesc('id');
    }

    public function photoUrl(): ?string
    {
        return Media::url($this->photo);
    }

    /**
     * "AH" — shown in a circle when there is no photograph.
     *
     * Which is most of the time, on purpose: the demo ships without patient
     * photographs, because putting a stranger's face beside a quote about their
     * heart is not a trade most people would agree to.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->trim()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    /** Clamped to 1–5 so a bad value can never render six stars or none. */
    public function stars(): int
    {
        return max(1, min(5, $this->rating));
    }
}
