<?php

use App\Models\Faq;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The questions the chamber answers on the phone forty times a week.
 *
 * These are rendered as an accordion on /faq and marked up as schema.org
 * FAQPage, which is what lets Google show the answers directly in search
 * results — the single highest-value piece of structured data on a site like
 * this one.
 *
 * @see Faq
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();

            $table->string('question', 500);

            /*
             | Plain text rather than rich text, deliberately. Google's FAQPage
             | markup accepts only limited HTML, and an answer pasted in with
             | headings and images tends to be rejected silently. Keeping the
             | field plain means what the doctor types is what gets indexed.
             */
            $table->text('answer');

            /*
             | An optional grouping such as "Appointments" or "Fees and
             | payment". Left null, the question sits in a general list; the
             | public page only shows category headings once more than one
             | category is actually in use.
             */
            $table->string('category')->nullable();

            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
