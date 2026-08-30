<?php

use App\Models\HealthVideo;
use App\Support\VideoEmbed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The patient education library: short videos about a condition or procedure.
 *
 * A video is either uploaded to this site or embedded from YouTube or Vimeo.
 * The columns below cover both, and App\Support\VideoEmbed normalises whatever
 * URL the doctor pastes down to the bare ID stored here, so the public site
 * never has to parse a URL at render time.
 *
 * @see HealthVideo
 * @see VideoEmbed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_videos', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            /*
             | The disease or topic this video is about — "Heart failure",
             | "After your angiogram". The filter on the public library is built
             | from the distinct values of this column.
             |
             | A plain string rather than a categories table on purpose. A
             | single doctor will have somewhere between five and fifteen
             | topics, and making them a related model would buy referential
             | tidiness at the cost of a whole extra admin screen for the doctor
             | to manage before they can publish their first video.
             */
            $table->string('topic')->nullable()->index();

            // App\Enums\VideoType: upload | youtube | vimeo.
            $table->string('video_type', 10)->default('youtube');

            /*
             | Exactly what the doctor pasted, kept verbatim. When a video
             | mysteriously fails to play, this is the column that says why —
             | the normalised ID below has already thrown away the evidence.
             */
            $table->string('source_url')->nullable();

            // Normalised by VideoEmbed: 'dQw4w9WgXcQ', '76979871'.
            $table->string('video_id', 64)->nullable();

            /*
             | Vimeo's unlisted-video hash. It must be carried into the embed
             | URL as ?h=… or an unlisted video renders as "private" — which
             | looks exactly like a broken embed and is very hard to diagnose.
             */
            $table->string('video_hash', 32)->nullable();

            // Path on the `public` disk, when video_type is `upload`.
            $table->string('video_path')->nullable();

            /*
             | A thumbnail the doctor uploaded. Always wins when present.
             | Otherwise the model derives one — see HealthVideo::thumbnailUrl().
             */
            $table->string('thumbnail_path')->nullable();

            /*
             | Vimeo has no predictable thumbnail address, so it has to be
             | looked up through their oEmbed endpoint. That happens ONCE, when
             | the video is saved, and the answer is cached here. Fetching it at
             | render time would put a third-party HTTP call in the critical
             | path of a page load on shared hosting.
             */
            $table->string('remote_thumbnail_url')->nullable();

            $table->unsignedInteger('duration_seconds')->nullable();

            /*
             | Featured videos appear on the home page. Same reasoning as
             | services: without it the home page grows without limit.
             */
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->timestamps();

            $table->index(['is_published', 'published_at']);
            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_videos');
    }
};
