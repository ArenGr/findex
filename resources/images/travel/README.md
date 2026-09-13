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

### `preset-<key>-source.<jpg|png|webp>` — the popular trips

One photograph per card in the "Popular trips" row. **At least 640px wide**,
landscape, cropped to roughly 2:1 — each is painted in a panel about
240x128 CSS px, so 640 covers it on a retina screen and anything larger is
shipped for nothing.

| file | what it should show |
|---|---|
| `preset-georgia_break-source.*` | Tbilisi — old town, Narikala, the river |
| `preset-dubai_city-source.*` | Dubai — the Marina or the Burj skyline |
| `preset-egypt_all_in-source.*` | Hurghada — Red Sea resort coast, not the pyramids |
| `preset-cyprus_sea-source.*` | Ayia Napa — sea caves or the beach |

The city, not just the country: the card says "Hurghada", and a photograph of
Giza under it would describe a different holiday from the one the preset
actually asks for (5-star, all-inclusive, by the sea).

Each is optional and independent. A preset with no photograph on disk falls
back to its flag on a tinted panel, so the page works with none, some or all
four installed — nothing 404s and no markup changes.

### What is installed now, and under what licence

All four are **CC0 / public domain** — free for commercial use with no
attribution required. Found through the Openverse API, not a web image search:
photographs off a search engine are almost all rights-reserved, and this is a
commercial site.

| stem | photograph | licence | source |
|---|---|---|---|
| `preset-georgia_break` | Tbilisi Peace Bridge and Kura River, by Falco | CC0 | Wikimedia Commons |
| `preset-dubai_city` | Burj Al Arab | CC0 | rawpixel |
| `preset-egypt_all_in` | Red Sea watersports and yachts | CC0 | rawpixel |
| `preset-cyprus_sea` | Cyprus rocky coast | CC0 | rawpixel |

Two caveats worth knowing. The Egypt and Cyprus originals carry no precise
location, so they are regionally right rather than verified as Hurghada and
Ayia Napa specifically - which is why each card's `alt` names the trip rather
than asserting the place. And every source was pre-cropped to 2:1 with sharp's
`attention` strategy before being saved here, because the card frame is about
2.2:1 and the Dubai original is portrait: centred in a landscape frame it
showed the middle of the hotel and nothing else.

Replace any of them with a better photograph by dropping a new
`preset-<key>-source.*` here and re-running - nothing else changes.
