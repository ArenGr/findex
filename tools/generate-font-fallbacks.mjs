/**
 * Generates resources/css/fonts-fallbacks.css.
 *
 * Every face on this site is font-display: optional, so when a font is not
 * ready in time the browser keeps the fallback for that page view. Left alone,
 * that fallback is a system font with different metrics - different character
 * widths, different ascent - so the text sits at a different size and re-wraps
 * differently from the design. That is the "the text resizes" flash.
 *
 * The fix is a fallback @font-face per real face: it renders from a local
 * system font, but with size-adjust / ascent-override / descent-override /
 * line-gap-override computed from the real font, so it occupies exactly the
 * space the real one would. The metrics come from fontaine, which reads them
 * out of the actual font binaries.
 *
 * laravel-vite-plugin has an `optimizedFallbacks` option meant to do this for
 * the Bunny families, but in 3.x it hands readMetrics a bare filesystem path
 * where only a file:// URL works, so it silently produces nothing - and it
 * would not cover FreeSans, which is declared by hand, in any case. Hence
 * this script.
 *
 * Re-run after `npm run build` whenever a font file or weight changes:
 *
 *     npm run build && npm run fonts:fallbacks
 *
 * The output is committed, like tools/subset-freesans.py's output, because it
 * only changes when the fonts do.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { resolve } from 'node:path';
import { readMetrics, getMetricsForFamily } from 'fontaine';

/**
 * Local faces to derive fallbacks from, in the order the browser should try
 * them: the first local() that resolves on the visitor's machine wins. One
 * per platform - Windows, macOS, Android, then Arial as the universal last
 * resort (Linux resolves it to Liberation Sans through fontconfig).
 */
const LOCAL_FALLBACKS = ['Segoe UI', 'Helvetica Neue', 'Roboto', 'Arial', 'Liberation Sans', 'DejaVu Sans'];

/**
 * Metrics for locals that @capsizecss/metrics does not carry.
 *
 * Both are near-universal on Linux desktops, and naming them directly matters:
 * local("Arial") only reaches them through a fontconfig alias, which not every
 * browser honours. Read out of the shipped font files with fontaine, and
 * stable across distributions:
 *
 *   fc-match --format="%{file}\n" "Liberation Sans"
 *   node -e 'import("fontaine").then(f=>f.readMetrics(new URL("file:///path"))).then(console.log)'
 */
const EXTRA_METRICS = {
    'Liberation Sans': { ascent: 1854, descent: -434, lineGap: 67, unitsPerEm: 2048, xWidthAvg: 913 },
    'DejaVu Sans': { ascent: 1901, descent: -483, lineGap: 0, unitsPerEm: 2048, xWidthAvg: 1041 },
};

/** Real faces, and where to read each weight's metrics from. */
const FAMILIES = [
    // Hand-declared, so read straight from public/. The Latin subset is the
    // right one to measure: size-adjust is derived from average Latin
    // character width, which is what the local fallbacks are measured on too.
    {
        family: 'FreeSans',
        range: () => cssRange('resources/css/fonts-freesans.css'),
        weights: {
            400: 'public/fonts/subset/freesans-400-latin.woff2',
            700: 'public/fonts/subset/freesans-700-latin.woff2',
        },
    },
    // Downloaded by laravel-vite-plugin; resolved from the build manifest.
    { family: 'Montserrat', alias: 'montserrat', weights: [400, 500, 600, 700] },
    { family: 'Allerta Stencil', alias: 'allerta-stencil', weights: [400] },
    { family: 'Manrope', alias: 'manrope', weights: [400, 500, 600, 700] },
];

const manifest = JSON.parse(readFileSync('public/build/fonts-manifest.json', 'utf8'));

/** The built file for one weight of a Bunny family, preferring woff2. */
const fromManifest = (alias, weight) => {
    const variant = manifest.families[alias]?.variants[`${weight}:normal`];
    const file = variant?.files.find((f) => f.format === 'woff2') ?? variant?.files[0];

    if (!file) {
        throw new Error(`no built file for ${alias} ${weight} - run npm run build first`);
    }

    return `public/build/${file.file}`;
};

/**
 * Every code point the real family actually covers, as one unicode-range.
 *
 * Without this a fallback face claims *all* text, including scripts the real
 * font never had - and since it renders from a system font that may well have
 * them, it wins. Concretely: Montserrat has no Armenian, so Armenian headings
 * are supposed to fall through to FreeSans, but an unrestricted
 * "Montserrat fallback" resolving to DejaVu Sans swallowed them first and
 * quietly changed the face Armenian headings were set in.
 */
const manifestRange = (alias) => {
    const ranges = new Set();

    for (const variant of Object.values(manifest.families[alias].variants)) {
        for (const file of variant.files) {
            if (file.unicodeRange) ranges.add(file.unicodeRange);
        }
    }

    return [...ranges].join(',');
};

/** Same, read out of a hand-written stylesheet's @font-face blocks. */
const cssRange = (path) => {
    const ranges = [...readFileSync(path, 'utf8').matchAll(/unicode-range:\s*([^;]+);/g)]
        .map((m) => m[1].replace(/\s+/g, ' ').trim());

    return [...new Set(ranges)].join(',');
};

/**
 * Armenian, which no fallback face may claim.
 *
 * Every local() below is a Latin/Cyrillic face. FreeSans does cover Armenian,
 * so its fallback would inherit the range - and DejaVu Sans, which happens to
 * have Armenian glyphs, would then serve them. That is a real downgrade:
 * left alone the browser picks the system's actual Armenian face (Noto Sans
 * Armenian on most machines), which is what bold Armenian headings rendered
 * in before any of this, and which is designed for the script.
 *
 * FreeSans itself still serves regular Armenian - it is preloaded and comes
 * earlier in the stack. This only affects weights FreeSans has no Armenian
 * face for, which is where the browser was already falling through.
 */
const ARMENIAN = /U\+0530|U\+FB13/;

const withoutArmenian = (range) => range
    .split(',')
    .filter((part) => !ARMENIAN.test(part))
    .join(',');

const fallbackMetrics = Object.fromEntries(
    await Promise.all(LOCAL_FALLBACKS.map(async (name) => [
        name,
        EXTRA_METRICS[name] ?? await getMetricsForFamily(name),
    ]))
);

for (const [name, metrics] of Object.entries(fallbackMetrics)) {
    if (!metrics) throw new Error(`@capsizecss/metrics has no data for "${name}"`);
}

const blocks = [];

for (const { family, alias, weights, range } of FAMILIES) {
    const unicodeRange = withoutArmenian(range ? range() : manifestRange(alias));
    const entries = Array.isArray(weights)
        ? weights.map((w) => [w, fromManifest(alias, w)])
        : Object.entries(weights);

    for (const [weight, path] of entries) {
        // readMetrics takes a URL, not a path - handing it a bare path returns
        // null, which is exactly the bug that makes the plugin's own version
        // of this silently do nothing.
        const metrics = await readMetrics(pathToFileURL(resolve(path)));

        if (!metrics) throw new Error(`could not read metrics from ${path}`);

        for (const local of LOCAL_FALLBACKS) {
            const fb = fallbackMetrics[local];
            const sizeAdjust = (metrics.xWidthAvg / metrics.unitsPerEm) / (fb.xWidthAvg / fb.unitsPerEm);
            const em = metrics.unitsPerEm * sizeAdjust;
            const pct = (n) => `${(n * 100).toFixed(4).replace(/\.?0+$/, '')}%`;

            blocks.push([
                '@font-face {',
                `    font-family: "${family} fallback";`,
                `    font-weight: ${weight};`,
                '    font-style: normal;',
                `    src: local("${local}");`,
                `    size-adjust: ${pct(sizeAdjust)};`,
                `    ascent-override: ${pct(metrics.ascent / em)};`,
                `    descent-override: ${pct(Math.abs(metrics.descent) / em)};`,
                `    line-gap-override: ${pct(metrics.lineGap / em)};`,
                `    unicode-range: ${unicodeRange};`,
                '}',
            ].join('\n'));
        }
    }
}

const header = `/*
 * GENERATED by tools/generate-font-fallbacks.mjs - do not edit by hand.
 * Regenerate with: npm run build && npm run fonts:fallbacks
 *
 * One fallback face per real face per local system font. Each renders from a
 * font the visitor already has, resized and re-baselined to occupy exactly the
 * space the real face would, so a font that arrives late (or, under
 * font-display: optional, never arrives for that page view) does not change
 * the size of the text or re-wrap a single line.
 *
 * The browser uses the first local() that resolves, so the order within each
 * family runs Windows, macOS, Android, Arial, then the two faces Linux
 * desktops actually ship under their own names.
 *
 * Each face is limited to the code points the real family covers. A fallback
 * that claims more than its original would intercept scripts that are supposed
 * to fall through to the next family - Armenian headings, for one, which
 * Montserrat does not cover and FreeSans does.
 *
 * Known limit: size-adjust is derived from average *Latin* character width,
 * which is the only figure @capsizecss/metrics carries for the local faces.
 * The match is therefore exact for Latin, close for Cyrillic, and the two can
 * differ by a few percent on a page set entirely in Cyrillic. It shows up only
 * when a swap face arrives after first paint - measured at CLS 0.0005 on /ru,
 * against 0.11 before any of this work.
 *
 * These families are referenced from the @theme stacks in app.css.
 */\n\n`;

writeFileSync('resources/css/fonts-fallbacks.css', header + blocks.join('\n\n') + '\n');
console.log(`wrote resources/css/fonts-fallbacks.css - ${blocks.length} faces`);
