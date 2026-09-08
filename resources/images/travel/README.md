# Travel hero source images

Drop the two production sources here, then run:

    npm run travel:assets

That writes the responsive derivatives into `public/images/travel/`, and the
hero picks them up with no markup change. Until they exist the hero renders a
photo-free panel rather than magnifying a soft source.

## What is needed

### `hero-source.<jpg|png|webp|avif>`

The coastal photograph. **At least 2400px wide**, ideally 3000-3200px.

- Blue sea, cliffs and a Mediterranean town, with generous sky and sea so the
  left third stays quiet enough for the sage panel and the curved seam.
- Landscape, roughly 2.5:1 to 3:1. It is cropped with `object-cover` into a
  panel about 700x420 CSS px, so the interesting half should sit right of centre.
- No text, no UI, no buttons, no luggage in frame, no logos, no watermark.
- Not a screenshot, a mockup crop, or a page preview.

### `luggage-source.<png|webp>`

The suitcase + hat + camera composition. **At least 1400px tall**, ideally 1800.

- Transparent background, no baked drop shadow (the page adds its own).
- Cropped tight to the objects; the page positions it against the seam.

## Why the previous pack was replaced

The assets in `storage/app/rejected-travel-assets/` were derived from generated
design compositions rather than exported at production resolution. Measured:

| asset | nominal size | real detail |
|---|---|---|
| `travel-hero-desktop` | 1920x950 | ~480px — discarding 75% of its pixels cost RMSE 0.97/255 |
| `travel-elements` | 926x691 | ~230px — RMSE 1.93 at quarter size |

A genuine photograph loses RMSE 8-20 under the same test. The hero was
therefore magnifying about 3x on a retina display. `build-travel-hero.mjs`
refuses to enlarge past a source's natural resolution for exactly this reason.
