import { readFileSync, writeFileSync } from "node:fs";
import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";

const SUBSETS = ["latin", "latin-ext", "cyrillic", "cyrillic-ext"];
const FALLBACKS = ["Segoe UI", "Helvetica Neue", "Roboto", "Arial"];
const DISPLAY_PRELOADED = "optional";

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

            for (const family of Object.values(manifest.families)) {
                for (const variant of Object.values(family.variants)) {
                    const woff2 = variant.files.filter((file) => file.format === "woff2");
                    if (woff2.length > 0) variant.files = woff2;
                }
            }

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
                bunny("Montserrat", {
                    weights: [400, 500, 600, 700],
                    subsets: SUBSETS,
                    preload: false,
                    display: "swap",
                    fallbacks: FALLBACKS,
                }),
                bunny("Allerta Stencil", {
                    weights: [400],
                    display: DISPLAY_PRELOADED,
                    fallbacks: FALLBACKS,
                    subsets: ["latin"],
                    preload: true,
                }),
                bunny("Caveat", {
                    weights: [600],
                    subsets: ["latin", "cyrillic"],
                    display: DISPLAY_PRELOADED,
                    preload: true,
                    fallbacks: FALLBACKS,
                }),
                bunny("Plus Jakarta Sans", {
                    weights: [400, 500, 600, 700, 800],
                    subsets: SUBSETS,
                    preload: false,
                    display: "swap",
                    fallbacks: FALLBACKS,
                }),
                bunny("Manrope", {
                    weights: [400, 500, 600, 700],
                    subsets: SUBSETS,
                    preload: false,
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
