<?php

use App\Http\Middleware\HandleRedirects;
use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Managed 301 / 302 redirects.
 *
 * The reason this is in an SEO feature and not a routing one: almost every
 * buyer of this template is REPLACING a site. Whatever ranking their old pages
 * had is attached to the old URLs, and a launch that answers 404 on all of them
 * throws it away — usually without anyone noticing for a month, because the new
 * site looks fine.
 *
 * A doctor cannot edit routes/web.php. They can paste an old URL into a form.
 *
 * @see Redirect
 * @see HandleRedirects
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();

            /*
             | The old path, normalised to a leading slash and no query string
             | or trailing slash by the model's saving hook. Indexed and unique
             | because the middleware looks a path up on every request that
             | would otherwise 404 — and because two rows claiming the same
             | source is a conflict with no sensible resolution.
             */
            $table->string('from_path')->unique();

            // A path on this site, or an absolute URL to somewhere else.
            $table->string('to_path');

            /*
             | 301 permanent or 302 temporary. The default is 301 because that
             | is what passes ranking to the new URL, which is the entire point;
             | 302 exists for a page that really is coming back.
             */
            $table->unsignedSmallInteger('status_code')->default(301);

            $table->boolean('is_active')->default(true);

            /*
             | Usage, so a rule can be retired with evidence rather than nerve.
             | A redirect nobody has followed in a year is dead weight; one with
             | four thousand hits is load-bearing and must not be deleted.
             */
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();

            $table->string('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
