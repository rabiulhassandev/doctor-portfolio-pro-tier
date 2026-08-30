import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            /*
             | Three entry points:
             |   app.css / app.js  — the public website
             |   theme.css         — the Filament admin panel, loaded by
             |                       AdminPanelProvider's ->viteTheme() call
             */
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
            ],
            refresh: true,
            /*
             | Webfonts are downloaded from Bunny at build time and self-hosted,
             | so the finished site makes no request to Google. Bunny is a
             | drop-in mirror of the Google Fonts catalogue that does not log
             | visitor IPs — which matters more than usual on a medical site.
             |
             | Source Sans 3 is the text face, Newsreader the display face. The
             | Bengali face (SolaimanLipi) is NOT here: it is not on Bunny, so
             | it is committed under resources/fonts/ and declared by hand at
             | the top of resources/css/app.css.
             |
             | Nothing puts these on the page unless the layout calls
             | {{ Vite::fonts() }} — see components/layouts/app.blade.php.
             */
            fonts: [
                bunny('Source Sans 3', {
                    weights: [400, 500, 600],
                    styles: ['normal', 'italic'],
                }),
                bunny('Newsreader', {
                    weights: [400],
                    styles: ['normal', 'italic'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            // Blade's compiled-view cache churns constantly and would otherwise
            // trigger a full reload on every page render.
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
