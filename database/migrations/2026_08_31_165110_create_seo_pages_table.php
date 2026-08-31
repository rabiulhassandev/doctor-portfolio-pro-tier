<?php

use App\Models\SeoPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-page search settings for the FIXED pages.
 *
 * Articles and videos already carry their own meta columns, because each one is
 * a row the doctor creates. The home page, About, Services, Contact, the
 * gallery, the FAQ and the booking page are not rows — they are routes, with
 * their titles written into the Blade files. Until now there was no way to
 * change "Services" to "Cardiology services in Dhanmondi" without editing code,
 * which is precisely the edit a doctor doing their own SEO wants to make.
 *
 * Keyed by ROUTE NAME rather than by URL. A buyer who changes `/book` to
 * `/appointments` in routes/web.php keeps their tuned title and description;
 * keying by path would silently orphan the row.
 *
 * Every column is nullable and every one falls back — to the page's own
 * hard-coded title, then to the site defaults. A missing row is not a broken
 * page, it is an untouched one.
 *
 * @see SeoPage
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_pages', function (Blueprint $table) {
            $table->id();

            // 'home', 'about', 'videos.index'. One row per route, at most.
            $table->string('route_name')->unique();

            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('share_image')->nullable();

            /*
             | An absolute URL that overrides the self-referencing canonical.
             | Rarely needed and easy to get catastrophically wrong — pointing
             | every page at the home page is a well-known way to delete a site
             | from Google — so the form marks it advanced and the audit screen
             | flags any value that is not a URL on this domain.
             */
            $table->string('canonical_url')->nullable();

            $table->boolean('noindex')->default(false);
            $table->boolean('nofollow')->default(false);

            /*
             | Sitemap hints. Google has said for years that it largely ignores
             | both, and it is right to: a site that marks every page 1.0 is
             | telling it nothing. They are here because other engines and some
             | AI crawlers do still read them, and because they cost two small
             | columns.
             */
            $table->string('changefreq', 16)->nullable();
            $table->decimal('priority', 2, 1)->nullable();

            // Page-specific JSON-LD, merged with whatever the page already
            // emits rather than replacing it.
            $table->json('custom_schema')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_pages');
    }
};
