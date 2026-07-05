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
