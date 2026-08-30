<?php

use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The treatments and procedures the practice offers.
 *
 * @see Service
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();

            /*
             | A Heroicon name such as `heroicon-o-heart`, picked from a
             | dropdown in the admin panel rather than typed. Storing the name
             | instead of an uploaded image means the icons stay crisp at any
             | size and automatically follow the brand colour.
             */
            $table->string('icon')->nullable();

            $table->string('summary', 500)->nullable();   // One line, shown on the card.
            $table->text('description')->nullable();      // Full text, shown on /services.

            /*
             | Featured services appear on the home page; the rest only on the
             | services page. Without this the home page grows every time the
             | doctor adds a service and eventually becomes a list of thirty.
             */
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);

            // Written directly by dragging rows in the admin table.
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
