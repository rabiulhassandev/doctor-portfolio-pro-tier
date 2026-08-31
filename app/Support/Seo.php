<?php

namespace App\Support;

use App\Models\DoctorProfile;
use App\Models\SeoPage;
use App\Models\SeoSetting;
use Illuminate\Support\Str;

/**
 * Works out the search metadata for the page being rendered.
 *
 * The layout used to do this inline, and it was already three fallback chains
 * long before the admin settings existed. With per-page overrides on top it
 * would have been six, expressed in Blade, where nothing can be tested.
 *
 * So: one object, built once per render, that answers every question the
 * <head> has. The resolution order is stated once here rather than implied in
 * six places, which matters because getting it wrong is invisible — a page with
 * the wrong canonical looks completely normal and quietly loses its ranking.
 *
 * ORDER, first non-empty wins:
 *
 *   1. the admin's per-page override        (SEO → Page listings)
 *   2. what the page view passed in         (an article's own title)
 *   3. the site-wide default                (SEO → settings)
 *   4. the doctor's profile                 (Website content → Doctor profile)
 *   5. config/site.php                      (the developer's fallback)
 *
 * The admin override sits ABOVE the page's own value, which looks wrong until
 * you see which pages can have one. SeoPage rows exist only for the fixed
 * routes — home, about, services, contact and so on — and those pages' titles
 * are hard-coded in Blade. That hard-coded string is a DEFAULT written by
 * whoever built the template, and the entire point of the feature is letting a
 * doctor change "Services" to "Cardiology services in Dhanmondi" without
 * touching code. If the Blade value won, the override would silently do
 * nothing on every page it applies to.
 *
 * Articles and videos are unaffected: `blog.show` and `videos.show` are not
 * managed routes, so there is never a row to outrank them, and each record's
 * own meta fields live on its own form.
 */
class Seo
{
    private function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $image,
        public readonly string $canonical,
        public readonly string $robots,
        public readonly ?string $twitterHandle,
        public readonly bool $analyticsAllowed,
    ) {}

    /**
     * Build the metadata for this request.
     *
     * @param  string|null  $title  The page's own title, as passed to the layout.
     * @param  string|null  $description  The page's own description.
     * @param  string|null  $image  The page's own share image (a public-disk path).
     */
    public static function resolve(?string $title, ?string $description, ?string $image): self
    {
        $doctor = DoctorProfile::current();
        $settings = SeoSetting::current();
        $page = SeoPage::forCurrentRequest();

        $siteName = $doctor->name ?: config('site.name');

        $resolvedDescription = collect([
            $page?->description,
            $description,
            $settings->default_meta_description,
            $doctor->meta_description,
            $doctor->short_bio,
            config('site.meta_description'),
        ])->first(fn ($value): bool => filled($value)) ?? '';

        return new self(
            // See the class doc block for why the override outranks the page.
            title: $settings->buildTitle($page?->title ?: $title, $siteName),

            // Google truncates around 160 characters. Str::limit appends its
            // ellipsis AFTER the limit, so ask for 157 to land on 160.
            description: Str::limit(strip_tags($resolvedDescription), 157),

            image: Media::absoluteUrl(
                $page?->share_image
                ?: $image
                ?: $settings->default_share_image
                ?: $doctor->photo
            ),

            /*
             | Self-referencing unless the admin has deliberately pointed this
             | page at another. url()->current() drops the query string, which
             | is what we want — ?page=2 and ?topic=x are the same document to a
             | search engine and splitting them dilutes both.
             */
            canonical: $page?->canonical_url ?: url()->current(),

            robots: $settings->robotsDirective(
                pageNoindex: (bool) $page?->noindex,
                pageNofollow: (bool) $page?->nofollow,
            ),

            twitterHandle: $settings->twitterHandle(),

            analyticsAllowed: self::analyticsAllowedHere(),
        );
    }

    /**
     * Whether third-party tracking may load on this page.
     *
     * >>> This is a privacy rule, not a performance one. Do not relax it. <<<
     *
     * On a medical site the URL alone is sensitive: /book says someone is
     * arranging a consultation, and a patient account page says they are a
     * patient. Sending that to an analytics vendor is a different act from
     * telling them somebody read an article about blood pressure, and no
     * setting in the admin panel can switch this off — the doctor cannot
     * consent on the patient's behalf.
     *
     * Marketing pages still report, which is where the useful signal is anyway.
     */
    private static function analyticsAllowedHere(): bool
    {
        /*
         | Purely a question about WHICH PAGE this is. Whether anything is
         | actually configured is a separate question the layout asks per tag —
         | folding the two together here would have meant the doctor's own
         | custom <head> code was silently suppressed whenever they had not also
         | filled in a GA4 ID, which is a confusing thing to debug.
         */
        $routeName = request()->route()?->getName() ?? '';

        return ! Str::startsWith($routeName, ['patient.', 'payments.'])
            && $routeName !== 'booking'
            && ! request()->is('patient/*', 'payments/*', 'book', 'documents/*');
    }

    /**
     * schema.org markup describing the website and the organisation behind it.
     *
     * Distinct from the Physician block on the home, about and contact pages.
     * That one describes the PRACTICE — where it is, when it opens, what it
     * costs. This one describes the SITE, and it is what a search engine reads
     * to attach a name to the domain itself.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function siteSchema(): array
    {
        $doctor = DoctorProfile::current();
        $settings = SeoSetting::current();
        $siteName = $doctor->name ?: config('site.name');

        $organisation = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'MedicalOrganization',
            '@id' => url('/').'#organization',
            'name' => $doctor->chamber_name ?: $siteName,
            'legalName' => $settings->legal_name,
            'url' => url('/'),
            'logo' => Media::absoluteUrl($doctor->photo),
            'telephone' => $doctor->phone,
            'email' => $doctor->email,
            'foundingDate' => $settings->founding_year,
            'priceRange' => $settings->price_range,
            'sameAs' => $doctor->activeSocialLinks()->values()->all() ?: null,

            /*
             | The fields an assistant actually reasons over. "Is there a woman
             | cardiologist in Dhanmondi who consults in Bangla" is answered
             | from areaServed, availableLanguage and medicalSpecialty — not
             | from any amount of prose on the page.
             */
            'areaServed' => $settings->areas_served ?: null,
            'availableLanguage' => $settings->languages ?: null,
            'paymentAccepted' => $settings->payment_accepted
                ? implode(', ', $settings->payment_accepted)
                : null,
            'medicalSpecialty' => $doctor->specialization,
        ], fn ($value): bool => $value !== null && $value !== '' && $value !== []);

        $website = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => $siteName,
            'url' => url('/'),
            'inLanguage' => str_replace('_', '-', app()->getLocale()),
            'publisher' => ['@id' => url('/').'#organization'],
        ]);

        $schemas = [$organisation, $website];

        if (filled($settings->custom_schema)) {
            $schemas[] = $settings->custom_schema;
        }

        if ($page = SeoPage::forCurrentRequest()) {
            if (filled($page->custom_schema)) {
                $schemas[] = $page->custom_schema;
            }
        }

        return $schemas;
    }

    /**
     * The ownership-verification tags, as name => content.
     *
     * @return array<string, string>
     */
    public static function verificationTags(): array
    {
        $settings = SeoSetting::current();

        return array_filter([
            'google-site-verification' => $settings->google_verification,
            'msvalidate.01' => $settings->bing_verification,
            'yandex-verification' => $settings->yandex_verification,
            'p:domain_verify' => $settings->pinterest_verification,
        ], fn ($value): bool => filled($value));
    }
}
