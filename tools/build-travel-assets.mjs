#!/usr/bin/env node
/*
 * Builds the travel page's responsive raster set.
 *
 * Sources live in resources/images/travel/ as <name>-source.<ext>; derivatives
 * are written to public/images/travel/<name>-<width>.<avif|webp>.
 *
 * Every derivative is a DOWNSCALE. If a source is smaller than a target width
 * that width is skipped rather than enlarged - enlarging is how an earlier
 * asset pack ended up nominally 1920px wide while carrying about 480px of real
 * detail. `withoutEnlargement` enforces it a second time at the encoder.
 *
 * Alpha is preserved where a source has it. The hero is a photograph whose
 * edges fade out over roughly half its pixels, so it has to composite over
 * whatever the page puts behind it rather than carrying a baked background.
 */
import { existsSync, mkdirSync, readdirSync, statSync } from 'node:fs';
import { basename, extname, join } from 'node:path';
import sharp from 'sharp';

const SRC_DIR = 'resources/images/travel';
const OUT_DIR = 'public/images/travel';

const TARGETS = [
    // The hero photograph: the portrait crop, at every width.
    //
    // Its landscape sibling, hero-photo, is no longer built. The hero is a
    // framed card now rather than a full-bleed band, and that crop carries two
    // things baked into the pixels that only made sense in the band - a dashed
    // flight path in the sky and a cream blob in the corner - so it printed a
    // second flight path beside the page's own and there was no crop that
    // dropped it without also dropping the domes. hero-photo-source.png is
    // still in resources/images/travel/ if it is ever wanted back.
    { stem: 'hero-mobile', widths: [640, 900, 1080], minWidth: 1000 },
    // Empty-state illustration inside "Your trip"; painted at ~230px.
    { stem: 'trip-empty', widths: [320, 640, 960], minWidth: 900 },
    // Full-bleed closing band.
    { stem: 'panorama', widths: [960, 1440, 1916], minWidth: 1900 },

    // One photograph per popular trip, painted in a card panel about
    // 240x128 CSS px - so 640 covers it at 2x and there is nothing above that
    // worth shipping. A missing source is skipped rather than fatal: the card
    // falls back to a tinted panel, so the page works with none, some or all
    // four installed.
    { stem: 'preset-georgia_break', widths: [400, 640], minWidth: 640 },
    { stem: 'preset-dubai_city', widths: [400, 640], minWidth: 640 },
    { stem: 'preset-egypt_all_in', widths: [400, 640], minWidth: 640 },
    { stem: 'preset-cyprus_sea', widths: [400, 640], minWidth: 640 },
];

// AVIF first (smallest at equal quality), WebP as the broad fallback.
//
// WebP stores alpha in a separate lossless-ish plane, so a photograph whose
// edges fade across half its pixels costs three times as much there as an
// opaque one: the hero at q88/alphaQ90 was 707 KB against AVIF's 136 KB.
// Measured against the raw source, q82/alphaQ70 moves RMSE from 2.24 to 3.39
// - invisible on a photograph - and lands at 229 KB. Opaque sources keep the
// higher quality, which costs them nothing.
const ENCODERS = (hasAlpha) => [
    { ext: 'avif', options: { quality: 64, effort: 6, chromaSubsampling: '4:4:4' } },
    { ext: 'webp', options: hasAlpha ? { quality: 82, alphaQuality: 70, effort: 6 } : { quality: 88, effort: 6 } },
];

function findSource(stem) {
    if (!existsSync(SRC_DIR)) return null;
    const hit = readdirSync(SRC_DIR).find(
        (f) => basename(f, extname(f)) === `${stem}-source` && /\.(jpe?g|png|webp|avif|tiff?)$/i.test(f)
    );
    return hit ? join(SRC_DIR, hit) : null;
}

let built = 0;
const missing = [];

for (const target of TARGETS) {
    const src = findSource(target.stem);
    if (!src) {
        missing.push(`${SRC_DIR}/${target.stem}-source.<ext>`);
        continue;
    }

    const meta = await sharp(src).metadata();
    console.log(`\n${target.stem}: ${basename(src)} — ${meta.width}x${meta.height}${meta.hasAlpha ? ' (alpha)' : ''}`);

    if (target.minWidth && meta.width < target.minWidth) {
        console.warn(`  ! only ${meta.width}px wide; ${target.minWidth}px or more is expected here.`);
    }

    mkdirSync(OUT_DIR, { recursive: true });

    for (const width of target.widths) {
        if (width > meta.width) {
            console.log(`  ${String(width).padStart(4)}w  skipped (source is ${meta.width}px; never enlarged)`);
            continue;
        }
        for (const { ext, options } of ENCODERS(Boolean(meta.hasAlpha))) {
            const out = join(OUT_DIR, `${target.stem}-${width}.${ext}`);
            await sharp(src)
                .resize({ width, withoutEnlargement: true })
                .toFormat(ext, options)
                .toFile(out);
            const kb = (statSync(out).size / 1024).toFixed(0);
            console.log(`  ${String(width).padStart(4)}w  ${ext.padEnd(4)} ${kb.padStart(5)} KB  ${out}`);
            built++;
        }
    }
}

if (missing.length) {
    console.log('\nNo source for:');
    missing.forEach((m) => console.log(`  ${m}`));
}

console.log(`\n${built} file(s) written.`);
