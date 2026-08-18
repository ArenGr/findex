import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny, local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                local('FreeSans', {
                    alias: 'freesans',
                    variable: '--font-freesans',
                    src: 'public/fonts/FreeSans*.woff2',
                }),
                bunny('Montserrat', {
                    weights: [400, 500, 600, 700, 800],
                }),
                bunny('Allerta Stencil', {
                    weights: [400],
                }),
                // The travel request flow's own face (see the --font-manrope
                // block in app.css) - self-hosted through Bunny like the two
                // above rather than hot-linked to Google.
                bunny('Manrope', {
                    weights: [400, 500, 600, 700, 800],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
