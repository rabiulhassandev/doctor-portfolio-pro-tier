<?php

use App\Models\GalleryImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photographs of the chamber, the team and the equipment.
 *
 * @see GalleryImage
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_images', function (Blueprint $table) {
            $table->id();

            $table->string('image');                      // Path on the `public` disk.
            $table->string('caption')->nullable();        // Shown under the photo.

            /*
             | Separate from the caption because they do different jobs: the
             | caption is read by everyone, the alt text is read *instead of*
             | the photo by someone using a screen reader. The admin form
             | explains the difference, and falls back to the caption when this
             | is left empty — an imperfect alt is far better than none.
             */
            $table->string('alt_text')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_images');
    }
};
