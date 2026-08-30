<?php

/*
|--------------------------------------------------------------------------
| Site branding
|--------------------------------------------------------------------------
|
| >>> THIS IS THE FILE TO EDIT WHEN YOU REBRAND THE TEMPLATE FOR A NEW DOCTOR. <<<
|
| Everything that makes the site look and feel like a particular practice —
| the site name, the logo, the colour palette, the footer credit — lives here.
| Content (bio, services, blog posts, opening hours, availability, videos…) is
| managed by the doctor from the admin panel at /admin instead, so you should
| rarely need to touch anything outside this file and the seeders.
|
| The colour values are plain hex codes. They are injected into the page as CSS
| custom properties by resources/views/components/layouts/app.blade.php, and the
| Tailwind classes in the Blade views read those variables — so changing a hex
| code here restyles the whole public site without a rebuild.
|
| Anything a *developer* configures rather than a buyer — payment gateway keys,
| the booking horizon, the clinic timezone, SMS drivers — lives in
| config/booking.php instead. Keeping branding and operations apart means a
| non-technical reseller can safely edit this file and nothing else.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Identity
    |--------------------------------------------------------------------------
    |
    | `name` is the browser title suffix and the navbar wordmark. It falls back
    | to APP_NAME so a buyer can also set it from .env without editing code.
    |
    */

    'name' => env('APP_NAME', 'Dr. Nafis Ahmed Chowdhury'),

    'specialization' => 'Consultant Cardiologist',

    /*
     | Path to the logo, relative to the `public/` directory. Leave it null to
     | render the doctor's initials in a coloured circle instead — which looks
     | deliberate rather than broken, so the demo site needs no artwork.
     */
    'logo' => null,

    /*
    |--------------------------------------------------------------------------
    | Colour palette
    |--------------------------------------------------------------------------
    |
    | A calm, clinical blue/teal scheme on warm paper. `primary` carries buttons
    | and links; `accent` is used sparingly, for the things that should catch the
    | eye and nothing else. Swap the hex codes and the whole public site follows.
    |
    | The site is built on *paper*, not on white. A page of pure #ffffff panels
    | separated by pale blue bands is the look of a template; a warm near-white
    | with hairline rules is the look of a printed brochure, and that is the
    | difference the palette below is chasing.
    |
    */

    'colors' => [
        'primary' => '#0f5c86',        // Deep clinical blue — buttons, links.
        'primary_dark' => '#0a4363',   // Hover state for primary.
        'primary_light' => '#e6f2f8',  // Tinted panels, used sparingly.
        'accent' => '#14a5a0',         // Teal — the one colour that draws the eye.
        'accent_light' => '#e4f6f5',   // Tinted accent background.
        'ink' => '#16242e',            // Body copy.
        'muted' => '#61727e',          // Secondary copy.

        /*
         | Surfaces. `paper` is the page itself and `paper_shade` is the
         | alternating band — the two are close enough that the change reads as
         | a fold in the page rather than as a coloured stripe.
         */
        'paper' => '#fbfaf8',          // Page background.
        'paper_shade' => '#f4f2ee',    // Alternating sections.
        'surface' => '#ffffff',        // Cards sitting on the paper.

        /*
         | Hairlines. Borders are the detail that most gives a template away:
         | a 1px mid-blue outline around every card reads as a wireframe. These
         | two are barely-there warm greys, dark enough to describe an edge and
         | light enough to stay out of the way.
         */
        'line' => '#e4e0d9',           // Default hairline.
        'line_strong' => '#cfc9bf',    // Hover and emphasis.

        'ink_deep' => '#101c24',       // The footer, and anything inverted.
        'gold' => '#b08d57',           // Star ratings. Brass, not highlighter.

        /*
         | Status colours.
         |
         | The Pro tier shows appointment and payment states to patients, and
         | those need to read as states rather than as decoration. They are
         | muted on purpose — a booking confirmation in signal green next to
         | this palette looks like a system alert, not like a clinic.
         */
        'positive' => '#2f7a5a',       // Confirmed, paid, open.
        'positive_light' => '#e6f3ec',
        'caution' => '#a9741f',        // Pending, awaiting payment.
        'caution_light' => '#fbf1de',
        'negative' => '#a33a34',       // Cancelled, failed.
        'negative_light' => '#fbeceb',
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults used before the doctor fills in the admin settings
    |--------------------------------------------------------------------------
    |
    | These only show on a brand-new install with an empty database. Once the
    | Doctor Profile page has been saved, the database values win.
    |
    */

    'meta_description' => 'Consultant cardiologist in Dhaka. Echocardiography, ECG and blood pressure care. '
        .'Book an appointment online, watch patient education videos, and collect your reports.',

    /*
     | Pre-filled text for the floating WhatsApp button.
     */
    'whatsapp_message' => 'Assalamu alaikum. I would like to ask about an appointment.',

    /*
    |--------------------------------------------------------------------------
    | Feature switches
    |--------------------------------------------------------------------------
    |
    | Turn off any public section a particular buyer does not want, without
    | deleting the code (which would make future upgrades painful). Each switch
    | hides the page, its navigation link and its sitemap entry together.
    |
    | `booking` is the one to think about: switching it off turns the Pro tier
    | back into the Standard tier's "request an appointment" behaviour, which is
    | what a doctor who does not keep a predictable schedule actually wants.
    |
    */

    'features' => [
        'blog' => true,
        'gallery' => true,
        'testimonials' => true,
        'faq' => true,
        'health_videos' => true,
        'booking' => true,
        'whatsapp_button' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'blog_per_page' => 6,
    'videos_per_page' => 12,

    /*
    |--------------------------------------------------------------------------
    | Footer credit
    |--------------------------------------------------------------------------
    |
    | Shown in small print at the bottom of every page. Set to null to hide it.
    |
    */

    'credit' => null,

];
