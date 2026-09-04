import { readFileSync, writeFileSync } from "node:fs";
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

/**
 * Local faces to derive metric-matched fallbacks from.
 *
 * `optimizedFallbacks` (on by default) runs fontaine over these and emits a
 * "<Family> fallback" @font-face for each: same family, `src: local(...)`,
 * plus size-adjust/ascent-override/descent-override/line-gap-override
 * computed from the real font. The upshot is that the fallback occupies
 * exactly the space the real font will, so nothing re-wraps or changes size
 * when the real one arrives. It generated nothing before because this list
 * defaults to empty.
 *
 * One per platform, cheapest first: Segoe UI on Windows, Helvetica Neue on
 * macOS, Roboto on Android, Arial everywhere else (Linux resolves it to
 * Liberation Sans through fontconfig).
 */
const FALLBACKS = ["Segoe UI", "Helvetica Neue", "Roboto", "Arial"];

/**
 * `optional` is only safe on a face that is preloaded.
 *
 * It gives the font a ~100ms window and then decides, permanently for that
 * page view, whether to use it - a face that misses the window is not applied
 * even once it has finished downloading. That is what makes it flash-free, and
 * it is also why it must not be used on a face the page has not asked for up
 * front: the browser simply never renders that face at all.
 *
 * So it goes on the two that layouts/app.blade.php preloads - FreeSans, the
 * body face, and Allerta Stencil, the wordmark. Everything else stays on
 * `swap`. With FALLBACKS above making the stand-in metrically identical, a
 * swap no longer resizes or re-wraps anything; it only changes the shapes of
 * the letters once, which is a far better trade than a heading face that
 * usually never arrives.
 */
const DISPLAY_PRELOADED = "optional";

/**
 * Drops the WOFF copy of every face the font plugin downloads.
 *
 * It emits WOFF2 and WOFF as two separate @font-face rules with the same
 * family, weight and unicode-range - and CSS font matching takes the *last*
 * one, so every visitor was fetching the WOFF and the WOFF2 was dead weight.
 * That is 20,672 bytes of Montserrat 700 instead of 12,060, and it is why the
 * heading face arrived late enough to be seen swapping in. Allerta Stencil was
 * worse: preloaded as WOFF2 and then rendered from the WOFF, so both were
 * downloaded.
 *
 * WOFF2 has been supported by every browser since 2016, and this codebase
 * already relies on far newer CSS than that, so the WOFF is pure cost. There
 * is no option for this - laravel-vite-plugin's FORMATS is an internal
 * constant - so the manifest it writes is rewritten after the build. The
 * @fonts directive inlines its CSS from that manifest, which is why the
 * manifest rather than the emitted stylesheet is the thing to fix.
 */
function preferWoff2() {
    const manifestPath = "public/build/fonts-manifest.json";

    return {
        name: "findex:prefer-woff2",
        apply: "build",
        closeBundle() {
            const manifest = JSON.parse(readFileSync(manifestPath, "utf8"));

            for (const [alias, css] of Object.entries(manifest.style.familyStyles)) {
                manifest.style.familyStyles[alias] = css
                    .split(/\n\n(?=@font-face)/)
                    .filter((block) => !block.includes('format("woff")'))
                    .join("\n\n");
            }

            // Keep the file list in step: layouts/app.blade.php resolves the
            // files it preloads from here, and must not advertise a format the
            // stylesheet no longer references.
            for (const family of Object.values(manifest.families)) {
                for (const variant of Object.values(family.variants)) {
                    const woff2 = variant.files.filter((file) => file.format === "woff2");
                    if (woff2.length > 0) variant.files = woff2;
                }
            }

            // The stylesheet the manifest points at is what @fonts actually
            // inlines when it is called without arguments - familyStyles above
            // is only consulted for `@fonts('alias')`. Both have to be cleaned
            // or the page keeps shipping the WOFF rules.
            const stylePath = `public/build/${manifest.style.file}`;

            writeFileSync(stylePath, readFileSync(stylePath, "utf8")
                .split(/\n\n(?=@font-face)/)
                .filter((block) => !block.includes('format("woff")'))
                .join("\n\n"));

            writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));
        },
    };
}

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
                    // Still not preloaded: it would force all four subsets -
                    // Cyrillic included, on English pages - for decorative
                    // headings.
                    preload: false,
                    // Not preloaded, so not `optional`: see DISPLAY_PRELOADED.
                    display: "swap",
                    fallbacks: FALLBACKS,
                }),
                bunny("Allerta Stencil", {
                    weights: [400],
                    display: DISPLAY_PRELOADED,
                    fallbacks: FALLBACKS,
                    // The wordmark reads "Findex" in every locale, so Latin is
                    // the only subset that can ever be painted from this face.
                    subsets: ["latin"],
                    // The one face worth preloading. It is a single small file,
                    // it is above the fold on every page, and it is wide enough
                    // that swapping in late visibly moved the logo (and the
                    // header row with it) on each refresh - the objection to
                    // preloading is that it forces subsets nobody needs, and
                    // narrowing `subsets` above removes that.
                    preload: true,
                }),
                // The travel request flow's own face (see the --font-manrope
                // block in app.css). Never preloaded: it is used on the
                // travel pages only, so preloading it taxes every other page
                // on the site for nothing.
                bunny("Manrope", {
                    weights: [400, 500, 600, 700],
                    subsets: SUBSETS,
                    preload: false,
                    // Not preloaded, so not `optional`: see DISPLAY_PRELOADED.
                    display: "swap",
                    fallbacks: FALLBACKS,
                }),
            ],
        }),
        tailwindcss(),
        preferWoff2(),
    ],
    server: {
        watch: {
            ignored: ["**/storage/framework/views/**"],
        },
    },
});
