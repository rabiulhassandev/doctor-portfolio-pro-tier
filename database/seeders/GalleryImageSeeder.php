<?php

namespace Database\Seeders;

use App\Models\GalleryImage;
use Illuminate\Database\Seeder;

/**
 * Demo photographs of the chamber.
 *
 * >>> Stock photography, not pictures of any real practice. Replace them. <<<
 *
 * The files are copied onto the public disk by PlaceholderImageSeeder, which
 * must therefore run first — see DatabaseSeeder.
 */
class GalleryImageSeeder extends Seeder
{
    public function run(): void
    {
        $images = [
            ['image' => 'gallery/reception.jpg', 'caption' => 'Reception', 'alt_text' => 'The reception desk, with seating for waiting patients'],
            ['image' => 'gallery/waiting-area.jpg', 'caption' => 'Waiting area', 'alt_text' => 'A quiet waiting area with upholstered chairs and natural light'],
            ['image' => 'gallery/chamber.jpg', 'caption' => 'The consulting room', 'alt_text' => 'The consulting room, with a desk and an examination couch'],
            ['image' => 'gallery/echocardiography.jpg', 'caption' => 'Echocardiography', 'alt_text' => 'The echocardiography machine beside the examination couch'],
            ['image' => 'gallery/ecg-room.jpg', 'caption' => 'ECG', 'alt_text' => 'The ECG machine set up for a recording'],
            ['image' => 'gallery/procedure-room.jpg', 'caption' => 'Procedure room', 'alt_text' => 'A clean, well-lit procedure room'],
        ];

        foreach ($images as $index => $image) {
            GalleryImage::query()->updateOrCreate(
                ['image' => $image['image']],
                [...$image, 'sort_order' => $index],
            );
        }
    }
}
