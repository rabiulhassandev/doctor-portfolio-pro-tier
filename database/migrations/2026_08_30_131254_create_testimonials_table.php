<?php

use App\Models\Testimonial;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What patients have said about the practice.
 *
 * @see Testimonial
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('role')->nullable();           // "Patient since 2019", optional.

            /*
             | Nullable, and left empty by the demo seeder on purpose. Attaching
             | a stranger's face to a quote they never gave is bad enough; doing
             | it on a page about their cardiac history is worse, and most real
             | patients would rather their photograph were not there either. The
             | public card falls back to initials in a circle, which looks
             | deliberate rather than broken.
             */
            $table->string('photo')->nullable();

            $table->text('message');

            // 1–5. Rendered as brass stars, not highlighter-yellow ones.
            $table->unsignedTinyInteger('rating')->default(5);

            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
