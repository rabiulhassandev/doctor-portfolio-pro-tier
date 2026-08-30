<?php

namespace App\Models;

use App\Support\Media;
use Database\Factories\GalleryImageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A photograph of the chamber, the team or the equipment.
 *
 * @property string $image
 * @property string|null $caption
 * @property string|null $alt_text
 * @property int $sort_order
 */
class GalleryImage extends Model
{
    /** @use HasFactory<GalleryImageFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        return Media::url($this->image);
    }

    /**
     * Text read *instead of* the picture by a screen reader.
     *
     * Falls back to the caption, then to a generic description. An imperfect
     * alt is far better than an empty one, and this way the doctor gets
     * something usable even when they skip the field.
     */
    public function altText(): string
    {
        return $this->alt_text
            ?: ($this->caption ?: 'Photograph from the clinic gallery');
    }
}
