<?php

use App\Models\SeoSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site-wide search settings — one row, ever.
 *
 * Everything here used to be a constant somewhere: the title separator was in a
 * Blade file, the robots rules were a static public/robots.txt a buyer had to
 * edit over FTP, and there was nowhere at all to put a Search Console
 * verification code. All of it belongs to the person who owns the site, not to
 * the person who deploys it.
 *
 * The split with config/site.php is deliberate and worth keeping:
 *
 *   config/site.php  — how the site LOOKS. Edited once per buyer, by a
 *                      developer, in code, and it is a rebrand.
 *   this table       — how the site is FOUND. Edited continually, by the
 *                      doctor, in the admin panel, and it is operations.
 *
 * @see SeoSetting
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_settings', function (Blueprint $table) {
            $table->id();

            /*
            |------------------------------------------------------------------
            | The search listing
            |------------------------------------------------------------------
            */

            /*
             | How every <title> is assembled. `:page` is the page's own title
             | and `:site` is the practice name — "Book an appointment | Dr.
             | Tahmina Rahman". A template rather than a hard-coded separator
             | because the convention differs by market and by taste, and
             | because the home page usually wants the two the other way round.
             */
            $table->string('title_template')->default(':page | :site');

            // Used by any page that has not been given one of its own.
            $table->text('default_meta_description')->nullable();

            // The picture that appears when a link is shared. Public disk path.
            $table->string('default_share_image')->nullable();

            // For twitter:site / twitter:creator. Stored without the @.
            $table->string('twitter_handle', 64)->nullable();

            /*
            |------------------------------------------------------------------
            | Indexing
            |------------------------------------------------------------------
            */

            /*
             | The staging switch, and the single most dangerous field in the
             | panel in both directions: left on, a live site is invisible;
             | forgotten off, a half-built site gets indexed under the doctor's
             | name. The admin form shouts about it and the audit screen reports
             | it as a critical finding while it is on.
             */
            $table->boolean('discourage_indexing')->default(false);

            // Extra rules appended verbatim to the generated robots.txt.
            $table->text('robots_extra')->nullable();

            /*
            |------------------------------------------------------------------
            | AI and answer engines
            |------------------------------------------------------------------
            |
            | Which AI crawlers may read the site, as a map of user-agent =>
            | bool. Two quite different things are bundled together here on
            | purpose, because the doctor thinks of them as one question:
            |
            |   * SEARCH agents (OAI-SearchBot, PerplexityBot, Claude-SearchBot)
            |     fetch pages to CITE them in an answer. Blocking these is
            |     usually a mistake — it is the modern equivalent of blocking
            |     Googlebot.
            |   * TRAINING agents (GPTBot, ClaudeBot, CCBot, Google-Extended)
            |     collect text to train models. Whether to allow that is a
            |     values question, not an SEO one.
            |
            | The form labels say which is which, because the default of "allow
            | everything" is right for one and arguable for the other.
            */
            $table->json('ai_crawlers')->nullable();

            /*
             | llms.txt — a plain-Markdown summary of the site served at the
             | root, proposed by Jeremy Howard in 2024 as a way for a model to
             | get a page's substance without parsing navigation and scripts.
             |
             | NOT a ratified standard and not yet honoured by the major
             | assistants. It is here because it costs one route and one text
             | column, and because a medical site whose author has written a
             | clean summary of what they treat is exactly the case it was
             | designed for. Null means "generate one from the site content".
             */
            $table->text('llms_txt')->nullable();

            /*
            |------------------------------------------------------------------
            | Ownership verification
            |------------------------------------------------------------------
            |
            | The <meta> token each search engine hands you to prove you own the
            | domain. Stored as separate columns rather than a JSON blob because
            | each is pasted once and never touched again, and a typo in one
            | should not be able to invalidate the others.
            */
            $table->string('google_verification')->nullable();
            $table->string('bing_verification')->nullable();
            $table->string('yandex_verification')->nullable();
            $table->string('pinterest_verification')->nullable();

            /*
            |------------------------------------------------------------------
            | Analytics
            |------------------------------------------------------------------
            |
            | >>> READ THIS BEFORE FILLING ANY OF IT IN. <<<
            |
            | This is a MEDICAL site. A visitor's URL history here reveals what
            | they are worried might be wrong with them, and the booking and
            | patient pages carry more than that. Anything pasted into these
            | fields sends that to a third party.
            |
            | The application therefore never loads any of it on the patient
            | account or booking screens — see the layout — and ships with all
            | of it empty. Whether to fill it in is the practice's decision and
            | may be regulated where they operate.
            */
            $table->string('ga4_measurement_id', 32)->nullable();
            $table->string('gtm_container_id', 32)->nullable();
            $table->string('meta_pixel_id', 32)->nullable();

            /*
             | Escape hatches for a tag this template does not know about.
             | Rendered raw and unescaped, so they are exactly as safe as the
             | person with the admin password — which is the same trust level as
             | every other CMS that offers this, and the reason it is documented
             | as an advanced field.
             */
            $table->text('head_scripts')->nullable();
            $table->text('body_scripts')->nullable();

            /*
            |------------------------------------------------------------------
            | Structured data
            |------------------------------------------------------------------
            |
            | Extra facts for the Physician / LocalBusiness JSON-LD that the
            | doctor profile cannot supply. This is the block that actually
            | feeds an AI answer: an assistant asked "is there a woman
            | cardiologist in Dhanmondi who speaks Bangla" is reading structured
            | data, not prose.
            */
            $table->string('legal_name')->nullable();
            $table->string('founding_year', 4)->nullable();
            $table->string('price_range', 32)->nullable();
            $table->json('areas_served')->nullable();
            $table->json('languages')->nullable();
            $table->json('payment_accepted')->nullable();

            // Free-form JSON-LD merged into every page, for anything the fields
            // above do not cover.
            $table->json('custom_schema')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_settings');
    }
};
