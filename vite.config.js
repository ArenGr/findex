import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";

/**
 * Font policy
 *
 * The plugin preloads every WOFF2 variant by default. That is a <link
 * rel="preload">, which is a *download instruction*, not a hint - so every
 * page was fetching all 58 variants (756 KB) whether or not a single glyph
 * needed them: Greek and Vietnamese subsets, weight 800 that nothing uses,
 * and the travel flow's Manrope on pages that never render it.
 *
 * So preloading is now restricted to the faces that paint immediately on a
 * first view - body text and headings - and everything else is left to load
 * on demand, which is what @font-face and unicode-range already do well.
 *
 * `subsets` is narrowed to the scripts this site is actually written in
 * (Armenian, English, Russian). Armenian is not in any of these families'
 * subsets - it renders from FreeSans, which is unsubsetted and covers it.
 */
const SUBSETS = ["latin", "latin-ext", "cyrillic", "cyrillic-ext"];

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
            fonts: [
                // FreeSans (body and UI text) is not here: it is declared by
                // hand in resources/css/fonts-freesans.css. This provider
                // cannot express unicode-range, so it could only ship the
                // font as one ~109 KB file per weight that every visitor
                // downloaded in full. See tools/subset-freesans.py.
                bunny("Montserrat", {
                    // 800 dropped: font-extrabold/font-black appear nowhere
                    // in the views and the compiled CSS emits no
                    // font-weight: 800.
                    weights: [400, 500, 600, 700],
                    subsets: SUBSETS,
                    // Not preloaded. Preloading it forces all four subsets -
                    // Cyrillic included, on English pages - for decorative
                    // headings. font-display: swap already paints them in the
                    // fallback immediately, then swaps.
                    preload: false,
                }),
                bunny("Allerta Stencil", {
                    weights: [400],
                    subsets: SUBSETS,
                    // The logo wordmark only - a handful of glyphs, and the
                    // fallback is perfectly readable until it swaps.
                    preload: false,
                }),
                // The travel request flow's own face (see the --font-manrope
                // block in app.css). Never preloaded: it is used on the
                // travel pages only, so preloading it taxes every other page
                // on the site for nothing.
                bunny("Manrope", {
                    weights: [400, 500, 600, 700],
                    subsets: SUBSETS,
                    preload: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
