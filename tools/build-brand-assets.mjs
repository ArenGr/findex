#!/usr/bin/env node
/*
 * Builds the favicons and the header wordmark from the brand sources.
 *
 * Sources live in resources/images/brand/ as <name>-source.png; derivatives go
 * to public/ (the icons, which have to sit at the document root) and
 * public/images/logo/ (the wordmark).
 *
 * Every source arrives on a canvas far larger than its artwork - the icon
 * master draws the mark across about 38% of a 2048px square, and the wordmarks
 * carry a wide transparent margin. Shipped as-is, a 16px favicon would spend
 * nine of its pixels on empty space. So everything is trimmed to its own ink
 * first and re-padded here, which also means the icon and the wordmark end up
 * optically the same weight rather than whatever their canvases happened to
 * give them.
 */
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import sharp from 'sharp';

const SRC_DIR = 'resources/images/brand';
const ROOT_OUT = 'public';
const LOGO_OUT = 'public/images/logo';

/** Favicon sizes that go into favicon.ico, largest last. */
const ICO_SIZES = [16, 32, 48];

/** Standalone PNG icons browsers pick from the <link> tags. */
const PNG_ICONS = [16, 32, 180];

/**
 * How much of the square the mark fills.
 *
 * 0.82 for the browser tab: a favicon is read at 16px and every pixel of
 * margin is one it cannot use. 0.68 for the Apple touch icon, which iOS masks
 * to a rounded square and sits on a home screen next to icons that all leave a
 * similar breathing space.
 */
const FILL = 0.82;
const APPLE_FILL = 0.68;

/** iOS composites a transparent touch icon onto black, so it gets a ground. */
const APPLE_BACKGROUND = { r: 255, g: 255, b: 255, alpha: 1 };

function source(stem) {
    const path = join(SRC_DIR, `${stem}-source.png`);

    if (!existsSync(path)) {
        throw new Error(`missing source: ${path}`);
    }

    return path;
}

/** The source's own ink, with the canvas it was delivered on taken off. */
function trimmed(stem) {
    return sharp(source(stem)).trim({ threshold: 1 });
}

/**
 * The mark on a transparent square, scaled so it fills `fill` of the frame.
 *
 * `contain` rather than `cover`: the mark is taller than it is wide, and cover
 * would crop the leaf to make it square.
 */
async function square(size, fill, background) {
    const inner = Math.round(size * fill);
    const mark = await trimmed('findex-mark')
        .resize(inner, inner, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } })
        .png()
        .toBuffer();

    return sharp({
        create: {
            width: size,
            height: size,
            channels: 4,
            background: background ?? { r: 0, g: 0, b: 0, alpha: 0 },
        },
    })
        .composite([{ input: mark, gravity: 'centre' }])
        .png({ compressionLevel: 9 })
        .toBuffer();
}

/**
 * An ICO holding PNG-compressed entries.
 *
 * A 6-byte directory header, then one 16-byte entry per size, then the PNG
 * payloads. Every browser that still reads .ico accepts PNG entries, and the
 * alternative - a BMP payload per size - would mean hand-rolling the DIB
 * header and an AND mask for the alpha.
 */
function ico(images) {
    const header = Buffer.alloc(6);
    header.writeUInt16LE(0, 0); // reserved
    header.writeUInt16LE(1, 2); // 1 = icon
    header.writeUInt16LE(images.length, 4);

    const entries = [];
    let offset = 6 + images.length * 16;

    for (const { size, data } of images) {
        const entry = Buffer.alloc(16);
        entry.writeUInt8(size >= 256 ? 0 : size, 0); // 0 means 256
        entry.writeUInt8(size >= 256 ? 0 : size, 1);
        entry.writeUInt8(0, 2); // palette size
        entry.writeUInt8(0, 3); // reserved
        entry.writeUInt16LE(1, 4); // colour planes
        entry.writeUInt16LE(32, 6); // bits per pixel
        entry.writeUInt32LE(data.length, 8);
        entry.writeUInt32LE(offset, 12);
        entries.push(entry);
        offset += data.length;
    }

    return Buffer.concat([header, ...entries, ...images.map((i) => i.data)]);
}

async function main() {
    for (const dir of [ROOT_OUT, LOGO_OUT]) {
        if (!existsSync(dir)) mkdirSync(dir, { recursive: true });
    }

    // The browser tab, at the sizes the <link> tags name.
    for (const size of PNG_ICONS) {
        const apple = size === 180;
        const data = await square(size, apple ? APPLE_FILL : FILL, apple ? APPLE_BACKGROUND : null);
        const name = apple ? 'apple-touch-icon.png' : `favicon-${size}x${size}.png`;

        writeFileSync(join(ROOT_OUT, name), data);
        console.log(`${name.padEnd(28)} ${size}x${size}  ${(data.length / 1024).toFixed(1)} KB`);
    }

    const entries = [];
    for (const size of ICO_SIZES) {
        entries.push({ size, data: await square(size, FILL, null) });
    }

    const icoData = ico(entries);
    writeFileSync(join(ROOT_OUT, 'favicon.ico'), icoData);
    console.log(`${'favicon.ico'.padEnd(28)} ${ICO_SIZES.join('+')}  ${(icoData.length / 1024).toFixed(1)} KB`);

    // The header wordmark, and the lockup with the tagline for anywhere it can
    // be set large enough to read.
    for (const [stem, out, width] of [
        ['findex-wordmark', 'logo.png', 1200],
        ['findex-wordmark-tagline', 'logo-tagline.png', 1200],
    ]) {
        const data = await trimmed(stem)
            .resize({ width, withoutEnlargement: true })
            .png({ compressionLevel: 9 })
            .toBuffer();

        writeFileSync(join(LOGO_OUT, out), data);
        const { width: w, height: h } = await sharp(data).metadata();
        console.log(`${out.padEnd(28)} ${w}x${h}  ${(data.length / 1024).toFixed(1)} KB`);
    }
}

main().catch((error) => {
    console.error(error.message);
    process.exitCode = 1;
});
