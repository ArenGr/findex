/**
 * Reports what moves on a page between first paint and Alpine taking over.
 *
 * Two numbers per URL:
 *
 *   CLS      - Chrome's own layout-shift score for a cold, throttled load.
 *   "moved"  - every element whose box differs between a JavaScript-off
 *              render (what the server sent) and a settled JavaScript-on one.
 *              Width counts as much as height: an element that only appears
 *              once Alpine runs often does not lengthen the page, it narrows
 *              the column next to it, and every line in that column re-wraps.
 *              A non-empty list means the browser painted one layout and then
 *              had to redo it, which is what a visitor sees as the page
 *              flickering or jumping on refresh. It catches shifts below the
 *              fold too, which CLS by definition does not.
 *
 * The usual causes are markup that only exists once Alpine runs (a
 * <template x-for>), an x-cloak on something the server could have rendered
 * in its final state, or a class/label that lives only in an Alpine binding.
 *
 * One false positive to know about: the two renders are two separate
 * requests, so a section whose content varies per request - the "similar
 * organizations" list on an organization page, say - reports a width change
 * that is just different text, not a shift. Check the reported element
 * before believing it.
 *
 * Usage: node tools/check-layout-shift.mjs http://localhost:8000/hy [more urls]
 *        node tools/check-layout-shift.mjs --width 390 http://localhost:8000/hy
 *
 * Requires `npm run build` to have run, and a server to be up.
 */
import { chromium } from 'playwright';

const args = process.argv.slice(2);
let width = 1280;
const widthFlag = args.indexOf('--width');
if (widthFlag !== -1) {
    width = Number(args[widthFlag + 1]);
    args.splice(widthFlag, 2);
}

if (args.length === 0) {
    console.error('usage: node tools/check-layout-shift.mjs [--width 390] <url> [url...]');
    process.exit(2);
}

const browser = await chromium.launch();
let failed = false;

/** Every element's box, keyed by its position in the tree. */
const heights = async (url, javaScriptEnabled) => {
    const context = await browser.newContext({ viewport: { width, height: 900 }, javaScriptEnabled });
    const page = await context.newPage();
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    // Long enough for Alpine to boot, run x-init and settle.
    await page.waitForTimeout(javaScriptEnabled ? 3000 : 1200);

    const result = await page.evaluate(() => {
        const out = {};
        const walk = (el, path) => {
            const box = el.getBoundingClientRect();
            // Width as well as height. A sidebar that only appears once
            // Alpine runs does not make the page taller - it makes the
            // column beside it narrower, and every paragraph in that column
            // re-wraps. That reads as the text resizing, and a height-only
            // check never sees it.
            out[path] = [Math.round(box.width), Math.round(box.height)];

            // Indexed loop, not spread: `children` is not iterable on every
            // node type the walk meets (SVG content, among others).
            const kids = el.children || [];
            for (let i = 0; i < kids.length; i++) {
                walk(kids[i], `${path}/${String(kids[i].tagName).toLowerCase()}${i}`);
            }
        };
        walk(document.body, 'body');
        return out;
    });

    await context.close();
    return result;
};

/** Chrome's layout-shift entries for a cold, throttled load. */
const cls = async (url) => {
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await context.newPage();
    const cdp = await context.newCDPSession(page);
    await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
    await cdp.send('Network.emulateNetworkConditions', {
        offline: false,
        latency: 60,
        downloadThroughput: (1.6 * 1024 * 1024) / 8,
        uploadThroughput: (750 * 1024) / 8,
    });

    await page.addInitScript(() => {
        window.__shifts = [];
        new PerformanceObserver((list) => {
            for (const entry of list.getEntries()) {
                if (!entry.hadRecentInput) window.__shifts.push(entry.value);
            }
        }).observe({ type: 'layout-shift', buffered: true });
    });

    await page.goto(url, { waitUntil: 'load', timeout: 60000 });
    await page.waitForTimeout(5000);
    const shifts = await page.evaluate(() => window.__shifts);
    await context.close();

    return shifts.reduce((total, value) => total + value, 0);
};

for (const url of args) {
    const before = await heights(url, false);
    const after = await heights(url, true);

    const moved = Object.keys(before)
        .filter((path) => path in after)
        .map((path) => ({
            path,
            dw: after[path][0] - before[path][0],
            dh: after[path][1] - before[path][1],
            from: before[path],
            to: after[path],
        }))
        // A box that is zero-height both before and after cannot push anything
        // around, however much its width changes - modal and overlay wrappers
        // sit at 0x0 until they are opened.
        .filter((row) => row.from[1] > 0 || row.to[1] > 0)
        .filter((row) => Math.abs(row.dw) >= 20 || Math.abs(row.dh) >= 20)
        .sort((a, b) => Math.max(Math.abs(b.dw), Math.abs(b.dh)) - Math.max(Math.abs(a.dw), Math.abs(a.dh)));

    const score = await cls(url);
    const ok = moved.length === 0 && score < 0.01;
    failed ||= !ok;

    console.log(`${ok ? 'ok  ' : 'FAIL'} ${url} @${width}  CLS=${score.toFixed(4)}  moved=${moved.length}`);
    for (const row of moved.slice(0, 10)) {
        const delta = `${row.dw >= 0 ? '+' : ''}${row.dw}w ${row.dh >= 0 ? '+' : ''}${row.dh}h`;
        console.log(`       ${delta.padStart(14)}  ${row.from.join('x')} -> ${row.to.join('x')}  ${row.path}`);
    }
}

await browser.close();
process.exit(failed ? 1 : 0);
