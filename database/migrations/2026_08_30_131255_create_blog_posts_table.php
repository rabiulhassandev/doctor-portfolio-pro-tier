<?php

use App\Models\BlogPost;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Articles the doctor writes for patients.
 *
 * @see BlogPost
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('cover_image')->nullable();
            $table->string('excerpt', 500)->nullable();

            // Rich text from the admin panel's editor, rendered unescaped.
            // Never pipe visitor-submitted content through this column.
            $table->longText('content');

            /*
             | Publishing needs BOTH of these: the toggle switched on AND the
             | date passed. That is what lets the doctor write a draft, and also
             | schedule a finished article for next Tuesday. The rule itself
             | lives in BlogPost::scopePublished() so it is never re-derived.
             */
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->timestamps();

            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
