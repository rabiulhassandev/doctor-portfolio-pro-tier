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
| There are TWO palettes below, and they are deliberately different:
|
|   'colors' — the public website. Dark, photographic, brass-accented.
|   'admin'  — the staff panel. Bright, blue, dense, built for working in.
|
| A patient should feel they have arrived somewhere considered. A receptionist
| at six in the evening wants contrast and legibility, not atmosphere. Trying
| to serve both with one palette makes a worse job of each.
|
| Anything a *developer* configures rather than a buyer — payment gateway keys,
| the booking horizon, the clinic timezone, SMS drivers — lives in
| config/booking.php instead.
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
     | render the doctor's initials in a brass-ruled square instead — which
     | looks deliberate rather than broken, so the demo site needs no artwork.
     */
    'logo' => null,

    /*
    |--------------------------------------------------------------------------
    | Public website palette
    |--------------------------------------------------------------------------
    |
    | A dark, photographic scheme: deep navy carrying the hero, the calls to
    | action and the footer, with brass as the single accent and a warm
    | off-white for everything that has to be read at length.
    |
    | The discipline that makes this work is restraint with the brass. It marks
    | one thing per view — the primary action, the active navigation item, a
    | rule under a heading — and nothing else. Brass on every heading, every
    | icon and every border is how a luxury palette turns into a gaudy one.
    |
    | Long-form text (articles, the patient dashboard) sits on `paper`, not on
    | `night`. A full page of body copy reversed out of near-black is hard work
    | for anybody, and this site's readers are frequently older than average.
    |
    */

    'colors' => [
        /*
         | The dark family. `night` is the hero, the footer and the closing
         | call to action; `night_soft` is a panel raised off it; `night_line`
         | is the hairline that separates them.
         */
        'night' => '#0B1620',          // The deepest surface. Hero, footer.
        'night_soft' => '#132433',     // Raised panels on the dark.
        'night_line' => '#25384A',     // Hairlines on the dark.

        /*
         | Brass. The one colour that draws the eye, used once per view.
         | Warm and slightly desaturated — a bright gold reads as a discount
         | banner, not as a considered detail.
         */
        'brass' => '#C8A45C',
        'brass_bright' => '#DCBB77',   // Hover, and small text on dark.
        'brass_soft' => '#F3EADA',     // Tinted panels on the light side.

        /*
         | The light family, for everything meant to be read at length.
         | `paper` is warm rather than white — pure #ffffff beside near-black
         | is glare, and the warmth is what stops the light sections feeling
         | like a different website from the dark ones.
         */
        'paper' => '#F8F6F2',          // Page background on light sections.
        'paper_shade' => '#EFEBE3',    // Alternating bands.
        'surface' => '#FFFFFF',        // Cards sitting on the paper.

        'ink' => '#111C26',            // Body copy on light.
        'muted' => '#5E6B78',          // Secondary copy on light.

        /*
         | Hairlines. Borders are the detail that most gives a template away:
         | a 1px mid-grey outline around every card reads as a wireframe.
         */
        'line' => '#E2DCD1',
        'line_strong' => '#CDC4B4',

        /*
         | Status colours. Muted on purpose — a booking confirmation in signal
         | green next to this palette looks like a system alert, not a clinic.
         */
        'positive' => '#2F7A5A',
        'positive_light' => '#E4F1EA',
        'caution' => '#A9741F',
        'caution_light' => '#FAF0DC',
        'negative' => '#A83A34',
        'negative_light' => '#FAEBEA',
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin panel palette
    |--------------------------------------------------------------------------
    |
    | Deliberately nothing like the public site. This is a working tool: a blue
    | header bar, a white navigation column, a light grey canvas, and solid blue
    | summary cards. Bright, high-contrast, and immediately legible.
    |
    | Only `primary` reaches Filament's own colour system; the rest are handed
    | to resources/css/filament/admin/theme.css as CSS custom properties by
    | AdminPanelProvider, so that stylesheet contains no hex codes at all and a
    | rebrand stays a change to this file.
    |
    */

    'admin' => [
        'primary' => '#4F7FE8',        // Topbar, active states, stat cards.
        'primary_dark' => '#3C67C6',   // Hover.
        'sidebar' => '#FFFFFF',        // The navigation column.
        'sidebar_ink' => '#3F4A57',    // Navigation labels.
        'canvas' => '#F4F6F9',         // The page behind the cards.
        'brand_tint' => '#EAF0FD',     // The logo block and active menu item.
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
    | Shown in small print at the bottom of the public site and in the admin
    | panel's footer. Set to null to hide it. HTML is allowed, so a link works.
    |
    */

    'credit' => null,

];
