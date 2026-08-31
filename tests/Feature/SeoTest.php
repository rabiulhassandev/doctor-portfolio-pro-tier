<?php

use App\Models\DoctorProfile;
use App\Models\Patient;
use App\Models\Redirect;
use App\Models\SeoPage;
use App\Models\SeoSetting;
use App\Support\SeoAudit;

/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
|
| The dangerous thing about this feature is that every failure mode is SILENT.
| A wrong canonical, a stray noindex, a robots.txt that blocks everything — the
| site keeps rendering perfectly and simply stops being found, and nobody
| notices for a month. So the tests here lean towards asserting what must NOT
| be in the output.
|
*/

beforeEach(function () {
    freezeClinicClock();

    DoctorProfile::create([
        'name' => 'Dr. Tahmina Rahman',
        'specialization' => 'Consultant Cardiologist',
        'phone' => '+8801700000000',
        'address_line' => '12 Dhanmondi',
        'city' => 'Dhaka',
        'working_hours' => DoctorProfile::defaultWorkingHours(),
    ]);

    DoctorProfile::forgetCurrent();
    SeoSetting::forgetCurrent();
    SeoPage::forgetAll();
});

describe('robots.txt', function () {
    it('is generated, not served from a file on disk', function () {
        // A real public/robots.txt would be served by the web server before
        // Laravel saw the request, silently overriding every admin setting.
        expect(file_exists(public_path('robots.txt')))->toBeFalse();
    });

    it('allows crawling and points at the sitemap by default', function () {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee('User-agent: *')
            ->assertSee('Allow: /')
            ->assertSee('Sitemap: '.route('sitemap'))
            // The private areas stay out of the index.
            ->assertSee('Disallow: /patient/')
            ->assertSee('Disallow: /admin');
    });

    it('blocks only the AI crawlers that were switched off', function () {
        SeoSetting::create([
            'title_template' => ':page | :site',
            'ai_crawlers' => ['GPTBot' => false, 'OAI-SearchBot' => true],
        ]);
        SeoSetting::forgetCurrent();

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        expect($body)->toContain("User-agent: GPTBot\nDisallow: /")
            ->and($body)->not->toContain('User-agent: OAI-SearchBot');
    });

    it('treats an unconfigured crawler as allowed', function () {
        // A doctor who has never opened the screen must still be findable.
        SeoSetting::create(['title_template' => ':page | :site', 'ai_crawlers' => null]);
        SeoSetting::forgetCurrent();

        expect(SeoSetting::current()->allowsCrawler('PerplexityBot'))->toBeTrue()
            ->and($this->get('/robots.txt')->getContent())->not->toContain('Disallow: /'."\n".'User-agent');
    });

    it('disallows everything while the staging switch is on', function () {
        SeoSetting::create(['title_template' => ':page | :site', 'discourage_indexing' => true]);
        SeoSetting::forgetCurrent();

        $body = $this->get('/robots.txt')->assertOk()->getContent();

        expect($body)->toContain("User-agent: *\nDisallow: /")
            // Contradicting itself with an Allow further down would make the
            // file unreliable — several crawlers resolve by longest match.
            ->and($body)->not->toContain('Allow: /');
    });

    it('appends the extra rules from the admin panel', function () {
        SeoSetting::create([
            'title_template' => ':page | :site',
            'robots_extra' => 'Disallow: /secret-page',
        ]);
        SeoSetting::forgetCurrent();

        $this->get('/robots.txt')->assertSee('Disallow: /secret-page');
    });
});

describe('llms.txt', function () {
    it('is generated from the practice content', function () {
        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
            ->assertSee('# Dr. Tahmina Rahman')
            ->assertSee('Consultant Cardiologist');
    });

    it('serves the admin override verbatim when one is written', function () {
        SeoSetting::create([
            'title_template' => ':page | :site',
            'llms_txt' => '# Written by hand',
        ]);
        SeoSetting::forgetCurrent();

        $this->get('/llms.txt')
            ->assertOk()
            ->assertSee('# Written by hand')
            ->assertDontSee('Consultant Cardiologist');
    });

    it('does not leak the admin panel\'s own coaching text', function () {
        /*
         | SeoPage::MANAGED carries hints written for whoever fills the form in
         | — "Worth targeting your specialisation and city". Publishing those
         | would describe the practice in the second person and hand out its
         | strategy at the same time.
         */
        $this->get('/llms.txt')
            ->assertOk()
            ->assertDontSee('Worth targeting your specialisation')
            ->assertDontSee('Your training and experience');
    });
});

describe('the page head', function () {
    it('builds the title from the admin template', function () {
        SeoSetting::create(['title_template' => ':page — :site']);
        SeoSetting::forgetCurrent();

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('<title>About Dr. Tahmina Rahman — Dr. Tahmina Rahman</title>', escape: false);
    });

    it('never repeats the site name against itself on the home page', function () {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Dr. Tahmina Rahman | Dr. Tahmina Rahman');
    });

    it('lets an admin override a fixed page\'s title and description', function () {
        SeoPage::create([
            'route_name' => 'services',
            'title' => 'Cardiology services in Dhanmondi',
            'description' => 'Echocardiography, ECG and blood pressure care.',
        ]);
        SeoPage::forgetAll();

        $this->get(route('services'))
            ->assertOk()
            ->assertSee('Cardiology services in Dhanmondi')
            ->assertSee('Echocardiography, ECG and blood pressure care.');
    });

    it('emits a self-referencing canonical', function () {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('about').'">', escape: false);
    });

    it('honours a canonical override', function () {
        SeoPage::create(['route_name' => 'about', 'canonical_url' => 'https://example.com/elsewhere']);
        SeoPage::forgetAll();

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('<link rel="canonical" href="https://example.com/elsewhere">', escape: false);
    });

    it('asks for large image previews when the page is indexable', function () {
        // The cheapest available win on a site with photographs: it is the
        // difference between a full-size thumbnail and a postage stamp.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('max-image-preview:large', escape: false);
    });

    it('marks a page noindex when the admin has hidden it', function () {
        SeoPage::create(['route_name' => 'gallery', 'noindex' => true]);
        SeoPage::forgetAll();

        $this->get(route('gallery'))
            ->assertOk()
            ->assertSee('content="noindex, follow"', escape: false);
    });

    it('hides the whole site while the staging switch is on', function () {
        SeoSetting::create(['title_template' => ':page | :site', 'discourage_indexing' => true]);
        SeoSetting::forgetCurrent();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('content="noindex, nofollow"', escape: false);
    });

    it('prints the verification tags the doctor pasted in', function () {
        SeoSetting::create([
            'title_template' => ':page | :site',
            'google_verification' => 'abc123',
            'bing_verification' => 'def456',
        ]);
        SeoSetting::forgetCurrent();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<meta name="google-site-verification" content="abc123">', escape: false)
            ->assertSee('<meta name="msvalidate.01" content="def456">', escape: false);
    });

    it('publishes organisation markup for search engines and assistants', function () {
        SeoSetting::create([
            'title_template' => ':page | :site',
            'languages' => ['Bengali', 'English'],
            'areas_served' => ['Dhanmondi'],
        ]);
        SeoSetting::forgetCurrent();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('"@type":"MedicalOrganization"', escape: false)
            ->assertSee('"@type":"WebSite"', escape: false)
            ->assertSee('"availableLanguage":["Bengali","English"]', escape: false);
    });
});

describe('analytics and patient privacy', function () {
    beforeEach(function () {
        SeoSetting::create([
            'title_template' => ':page | :site',
            'ga4_measurement_id' => 'G-ABCDEF123',
            'head_scripts' => '<!--custom-head-tag-->',
        ]);
        SeoSetting::forgetCurrent();
    });

    it('loads on an ordinary marketing page', function () {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('G-ABCDEF123')
            ->assertSee('custom-head-tag', escape: false);
    });

    /*
     | The rule that must not regress. On a medical site the URL alone says what
     | somebody is worried about, and the doctor cannot consent on a patient's
     | behalf — so no setting in the admin panel can switch these back on.
     */
    it('never loads on the booking page', function () {
        $this->get(route('booking'))
            ->assertOk()
            ->assertDontSee('G-ABCDEF123')
            ->assertDontSee('custom-head-tag', escape: false);
    });

    it('never loads inside a patient account', function () {
        $patient = Patient::factory()->create();

        $this->actingAs($patient, 'patient')
            ->get(route('patient.dashboard'))
            ->assertOk()
            ->assertDontSee('G-ABCDEF123');
    });

    it('never loads on the sign-in screen', function () {
        $this->get(route('patient.login'))
            ->assertOk()
            ->assertDontSee('G-ABCDEF123');
    });
});

describe('the sitemap', function () {
    it('leaves out a page the admin has hidden', function () {
        SeoPage::create(['route_name' => 'gallery', 'noindex' => true]);
        SeoPage::forgetAll();

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('about'))
            ->assertDontSee(route('gallery'));
    });

    it('uses the priority the admin set', function () {
        SeoPage::create(['route_name' => 'about', 'priority' => 0.3, 'changefreq' => 'yearly']);
        SeoPage::forgetAll();

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee('<priority>0.3</priority>', escape: false)
            ->assertSee('<changefreq>yearly</changefreq>', escape: false);
    });

    it('advertises nothing while the site is marked do-not-index', function () {
        // A sitemap listing pages that robots.txt has just told crawlers to
        // avoid is a contradiction, and Search Console reports it as one.
        SeoSetting::create(['title_template' => ':page | :site', 'discourage_indexing' => true]);
        SeoSetting::forgetCurrent();

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee(route('about'));
    });
});

describe('redirects', function () {
    it('sends a visitor from an old address to a new one', function () {
        Redirect::create(['from_path' => '/our-services', 'to_path' => '/services']);

        $this->get('/our-services')->assertRedirect(url('/services'))->assertStatus(301);
    });

    it('carries the query string across', function () {
        // Dropping it breaks the analytics of the campaign that produced the
        // click, which is usually the reason the link exists.
        Redirect::create(['from_path' => '/old', 'to_path' => '/services']);

        $this->get('/old?utm_source=facebook')
            ->assertRedirect(url('/services').'?utm_source=facebook');
    });

    it('counts how often each rule is used', function () {
        $redirect = Redirect::create(['from_path' => '/old', 'to_path' => '/services']);

        $this->get('/old');
        $this->get('/old');

        expect($redirect->fresh()->hits)->toBe(2)
            ->and($redirect->fresh()->last_hit_at)->not->toBeNull();
    });

    it('ignores a rule that has been switched off', function () {
        Redirect::create(['from_path' => '/old', 'to_path' => '/services', 'is_active' => false]);

        $this->get('/old')->assertNotFound();
    });

    it('still 404s when nothing matches', function () {
        $this->get('/no-such-page')->assertNotFound();
    });

    it('never shadows a real page', function () {
        /*
         | The fallback only runs when no route matched, so a rule claiming a
         | live URL can never fire. That is deliberate: a redirect quietly
         | intercepting /services would be extremely hard to diagnose.
         */
        Redirect::create(['from_path' => '/services', 'to_path' => '/about']);

        $this->get('/services')->assertOk()->assertDontSee('Location');
    });

    it('normalises whatever was pasted into the form', function (string $input) {
        $redirect = Redirect::create(['from_path' => $input, 'to_path' => '/services']);

        expect($redirect->from_path)->toBe('/our-services');
    })->with([
        '/our-services',
        'our-services',
        '/our-services/',
        'https://old-site.example/our-services/',
        'https://old-site.example/our-services?utm_source=fb',
    ]);
});

describe('the health check', function () {
    it('reports nothing critical on a well-configured site', function () {
        SeoSetting::create([
            'title_template' => ':page | :site',
            'default_meta_description' => 'A cardiologist in Dhaka.',
        ]);
        SeoSetting::forgetCurrent();

        expect(SeoAudit::summary()['critical'])->toBe(0);
    });

    it('reports the staging switch as critical', function () {
        SeoSetting::create(['title_template' => ':page | :site', 'discourage_indexing' => true]);
        SeoSetting::forgetCurrent();

        $findings = SeoAudit::run()->where('severity', SeoAudit::CRITICAL);

        expect($findings)->not->toBeEmpty()
            ->and($findings->first()['title'])->toContain('hidden from every search engine');
    });

    it('reports a canonical pointing at another website as critical', function () {
        SeoPage::create(['route_name' => 'about', 'canonical_url' => 'https://somewhere-else.example/']);
        SeoPage::forgetAll();

        expect(SeoAudit::run()->where('severity', SeoAudit::CRITICAL))->not->toBeEmpty();
    });

    it('warns when a search crawler is blocked but stays quiet about training', function () {
        SeoSetting::create([
            'title_template' => ':page | :site',
            'default_meta_description' => 'A cardiologist in Dhaka.',
            // Training off, search on. Only one of these is the audit's business.
            'ai_crawlers' => ['GPTBot' => false, 'OAI-SearchBot' => true],
        ]);
        SeoSetting::forgetCurrent();

        $titles = SeoAudit::run()->pluck('title')->implode(' | ');

        expect($titles)->not->toContain('blocked from AI search results');
    });

    it('warns when the site is blocked from AI search results', function () {
        SeoSetting::create([
            'title_template' => ':page | :site',
            'ai_crawlers' => ['OAI-SearchBot' => false],
        ]);
        SeoSetting::forgetCurrent();

        expect(SeoAudit::run()->pluck('title')->implode(' | '))
            ->toContain('blocked from AI search results');
    });
});

describe('title assembly', function () {
    it('applies the template', function () {
        $settings = new SeoSetting(['title_template' => ':page | :site']);

        expect($settings->buildTitle('About', 'Dr. Rahman'))->toBe('About | Dr. Rahman');
    });

    it('returns the site name alone for the home page', function () {
        $settings = new SeoSetting(['title_template' => ':page | :site']);

        expect($settings->buildTitle(null, 'Dr. Rahman'))->toBe('Dr. Rahman')
            ->and($settings->buildTitle('Dr. Rahman', 'Dr. Rahman'))->toBe('Dr. Rahman');
    });

    it('falls back to a sane template when the field is empty', function () {
        $settings = new SeoSetting(['title_template' => '']);

        expect($settings->buildTitle('About', 'Dr. Rahman'))->toBe('About | Dr. Rahman');
    });
});
