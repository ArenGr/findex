# Findex brand sources

The originals the favicons and the header lockup are built from. After
changing any of them:

    npm run brand:assets

That writes into `public/` (the icons, which have to sit at the document root)
and `public/images/logo/`. Nothing else has to change - the layouts and
`<x-brand-logo>` point at fixed filenames.

## What is here

| source | what it is | what it becomes |
|---|---|---|
| `findex-mark-source.png` | the leaf mark alone, 2048², transparent | `favicon.ico` (16/32/48), `favicon-16x16.png`, `favicon-32x32.png`, `apple-touch-icon.png` |
| `findex-wordmark-source.png` | mark + "Findex", transparent | `images/logo/logo.png` |
| `findex-wordmark-tagline-source.png` | the same with "Your Guide to Better Choices" | `images/logo/logo-tagline.png` |

## Why the build trims them

Each source is drawn on a canvas much bigger than its artwork - the mark fills
about 38% of its square, and both wordmarks carry a wide transparent margin.
Shipped as delivered, a 16px favicon spends nine of its sixteen pixels on empty
space. `tools/build-brand-assets.mjs` trims every source to its own ink and
re-pads it, so the icon and the wordmark come out optically the same weight
whatever canvas they arrived on.

## Which lockup goes where

`logo.png` - the one without the tagline - is what `<x-brand-logo>` paints,
because the header shows it 32px tall and the tagline would be about three
pixels of that. `logo-tagline.png` is built for anywhere it can be set large
enough to read.

The wordmark is near-black, so it needs a light ground. There is no light-on-dark
variant in the set; if one is ever needed it has to come from the designer
rather than be inverted here.
