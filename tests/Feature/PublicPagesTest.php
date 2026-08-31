<?php

use App\Models\AvailabilitySlot;
use App\Models\BlogPost;
use App\Models\DoctorProfile;
use App\Models\Faq;
use App\Models\GalleryImage;
use App\Models\HealthVideo;
use App\Models\Service;
use App\Models\Testimonial;

beforeEach(function () {
    freezeClinicClock();
    Cache::flush();

    DoctorProfile::create([
        'name' => 'Dr. Tahmina Rahman',
        'specialization' => 'Consultant Cardiologist',
        'chamber_name' => 'Anwara Heart Care',
        'registration_label' => 'BMDC Reg. No.',
        'registration_number' => 'A-42817',
        'short_bio' => 'Twenty years of cardiac practice in Dhaka.',
        'bio' => "First paragraph.\n\nSecond paragraph.",
        'philosophy' => 'Listen first.',
        'qualifications' => [
            ['title' => 'MBBS', 'institution' => 'Dhaka Medical College', 'year' => '2004'],
        ],
        'phone' => '+8801700000000',
        'email' => 'chamber@example.com',
        'whatsapp' => '8801700000000',
        'address_line' => '12 Dhanmondi',
        'city' => 'Dhaka',
        'country' => 'Bangladesh',
        'map_latitude' => 23.7461,
        'map_longitude' => 90.3742,
        'working_hours' => DoctorProfile::defaultWorkingHours(),
        'consultation_fee' => 1500,
        'years_of_experience' => 20,
        'social_links' => ['facebook' => 'https://facebook.com/example'],
    ]);
    DoctorProfile::forgetCurrent();
});

describe('every public page renders', function () {
    beforeEach(function () {
        Service::factory()->featured()->create();
        Testimonial::factory()->create();
        GalleryImage::factory()->create();
        Faq::factory()->create();
        HealthVideo::factory()->featured()->create();
        AvailabilitySlot::factory()->create();

        $this->post = BlogPost::factory()->create();
        $this->video = HealthVideo::factory()->create();
    });

    it('renders each page', function (string $routeName) {
        $this->get(route($routeName))->assertOk();
    })->with([
        'home',
        'about',
        'services',
        'contact',
        'blog.index',
        'gallery',
        'faq',
        'videos.index',
        'booking',
    ]);

    it('renders an article', function () {
        $this->get(route('blog.show', $this->post))
            ->assertOk()
            ->assertSee($this->post->title);
    });

    it('renders a video page', function () {
        $this->get(route('videos.show', $this->video))
            ->assertOk()
            ->assertSee($this->video->title);
    });

    it('renders the sitemap as XML', function () {
        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('booking'))
            ->assertSee(route('blog.show', $this->post->slug));
    });
});

describe('drafts stay hidden', function () {
    it('hides an unpublished article from the list and from its own URL', function () {
        $draft = BlogPost::factory()->draft()->create();

        $this->get(route('blog.index'))->assertOk()->assertDontSee($draft->title);
        // Guessing the slug must not work either — binding alone knows nothing
        // about drafts.
        $this->get(route('blog.show', $draft))->assertNotFound();
    });

    it('hides a scheduled article until its date passes', function () {
        $scheduled = BlogPost::factory()->scheduled()->create();

        $this->get(route('blog.show', $scheduled))->assertNotFound();
    });

    it('hides an unpublished video', function () {
        $draft = HealthVideo::factory()->draft()->create();

        $this->get(route('videos.show', $draft))->assertNotFound();
        $this->get(route('videos.index'))->assertOk()->assertDontSee($draft->title);
    });

    it('hides an unpublished service', function () {
        $draft = Service::factory()->draft()->create();

        $this->get(route('services'))->assertOk()->assertDontSee($draft->title);
    });
});

describe('feature switches', function () {
    it('hides a page turned off in config/site.php', function (string $feature, string $routeName) {
        config()->set("site.features.{$feature}", false);

        $this->get(route($routeName))->assertNotFound();
    })->with([
        ['blog', 'blog.index'],
        ['gallery', 'gallery'],
        ['faq', 'faq'],
        ['health_videos', 'videos.index'],
        ['booking', 'booking'],
    ]);

    it('leaves a switched-off page out of the sitemap', function () {
        config()->set('site.features.health_videos', false);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee(route('videos.index'));
    });
});

describe('SEO and structured data', function () {
    it('publishes Physician markup on the pages that describe the practice', function (string $routeName) {
        $this->get(route($routeName))
            ->assertOk()
            ->assertSee('"@type":"Physician"', escape: false)
            ->assertSee('BMDC Reg. No.');
    })->with(['home', 'about', 'contact']);

    it('publishes FAQPage markup', function () {
        Faq::factory()->create(['question' => 'Do I need an appointment?']);

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('"@type":"FAQPage"', escape: false)
            ->assertSee('"@type":"Question"', escape: false);
    });

    it('publishes VideoObject markup on a video page', function () {
        $video = HealthVideo::factory()->create();

        $this->get(route('videos.show', $video))
            ->assertOk()
            ->assertSee('"@type":"VideoObject"', escape: false);
    });

    it('publishes Article markup on an article', function () {
        $post = BlogPost::factory()->create();

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('"@type":"Article"', escape: false);
    });

    it('gives each page its own title and description', function () {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('<title>About Dr. Tahmina Rahman | Dr. Tahmina Rahman</title>', escape: false)
            ->assertSee('og:title', escape: false)
            ->assertSee('rel="canonical"', escape: false);
    });
});

describe('page banners', function () {
    /*
     | The photograph behind each interior page's heading band. It is looked up
     | by route name in config/site.php, so a buyer changes the whole site's
     | photography in one file and no page view names an image.
     */

    it('uses the photograph configured for the page it is on', function () {
        config()->set('site.banners', [
            'default' => 'site/fallback.jpg',
            'services' => 'gallery/theatre.jpg',
        ]);

        $this->get(route('services'))
            ->assertOk()
            ->assertSee('gallery/theatre.jpg', escape: false)
            ->assertDontSee('site/fallback.jpg', escape: false);
    });

    it('falls back to the default for a page with no entry of its own', function () {
        config()->set('site.banners', ['default' => 'site/fallback.jpg']);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('site/fallback.jpg', escape: false);
    });

    it('still renders the band when no photograph is configured at all', function () {
        // A fresh install before the seeders have copied the demo images. The
        // band has to degrade to its plain dark treatment, not to a broken page
        // or an <img> pointing at nothing.
        config()->set('site.banners', []);

        $this->get(route('services'))
            ->assertOk()
            ->assertSee('Services')
            ->assertDontSee('<img src="" ', escape: false);
    });
});

describe('branding', function () {
    it('injects the palette from config/site.php as CSS variables', function () {
        // This is what makes a rebrand a one-file change with no rebuild.
        config()->set('site.colors.night', '#123456');
        config()->set('site.colors.brass', '#abcdef');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('--brand-night: #123456', escape: false)
            ->assertSee('--brand-brass: #abcdef', escape: false);
    });

    it('keeps the admin palette separate from the public one', function () {
        /*
         | The two are deliberately different — a dark brass-accented website
         | and a bright blue working panel. A change to one must not leak into
         | the other.
         */
        config()->set('site.admin.primary', '#0000ff');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('#0000ff', escape: false);
    });

    it('shows the WhatsApp button only when a number is set', function () {
        $this->get(route('home'))->assertOk()->assertSee('wa.me/8801700000000', escape: false);

        DoctorProfile::first()->update(['whatsapp' => null]);
        DoctorProfile::forgetCurrent();

        $this->get(route('home'))->assertOk()->assertDontSee('wa.me/');
    });
});
