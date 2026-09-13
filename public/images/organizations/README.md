# Organization logos

Each file is the organization's own mark, used to identify that organization
beside its own products. Named `<slug>.<ext>` - the slug is what
`OrganizationSeeder` sets `logo` to, as `/images/organizations/<slug>.<ext>`,
and the value is rendered straight into `<img src>`.

They are painted in a round avatar about 40-44px across (`h-10`/`h-11` with
`object-contain`), so a wide wordmark ends up small; that is the existing
treatment and applies to every mark here equally.

## Provenance

### Insurers - taken from each company's own website on 2026-09-11

| slug | file | source |
|---|---|---|
| `armenia-insurance` | `armenia-insurance.png` | `armeniainsurance.am/api/files/logo/logo-Armenia_insurance.png` — header wordmark, 216x50 |
| `ingo-armenia` | `ingo-armenia.svg` | supplied by Findex — the wordmark, 142x41 |
| `liga-insurance` | `liga-insurance.svg` | `liga.am/_next/static/media/logo-header.*.svg` — header wordmark |
| `nairi-insurance` | `nairi-insurance.svg` | supplied by Findex — the round mark, white on near-black |
| `rego-insurance` | `rego-insurance.png` | supplied by Findex — the wordmark, white on near-black |
| `sil-insurance` | `sil-insurance.svg` | `silinsurance.am/image/catalog/icon-v.1.svg` — the app icon, taken from the page's own `og:image` |

### Banks - taken from each bank's own website on 2026-09-13

`acba`, `ameria` and `unibank` predate this note. The rest:

| slug | file | source |
|---|---|---|
| `aeb` | `aeb.svg` | header logo, inlined in the page's own markup |
| `amio` | `amio.svg` | header logo, inlined in the page's own markup |
| `araratbank` | `araratbank.png` | cropped out of `araratbank.am/img/sprite.png` at the header logo's own background-position, 213x26 |
| `ardshinbank` | `ardshinbank.svg` | `ardshinbank.am/logos/logo-header.svg` |
| `armswissbank` | `armswissbank.png` | `armswissbank.am/images/armswissbank_logo_en.png`, resized 677px -> 440px wide |
| `artsakhbank` | `artsakhbank.svg` | `artsakhbank.am/storage/settings/group.svg` |
| `byblos` | `byblos.svg` | header logo, inlined in the page's own markup |
| `conversebank` | `conversebank.svg` | header logo, inlined in the page's own markup |
| `evoca` | `evoca.png` | cropped out of `evoca.am/img/sprite.png` at the header logo's own background-position, 140x26 |
| `fastbank` | `fastbank.png` | the app icon, largest frame (64x64) of `fastbank.am/favicon.ico` |
| `idbank` | `idbank.svg` | `idbank.am/images/m-logo.svg` — the mark plus wordmark |
| `ineco` | `ineco.svg` | `inecobank.am/static/assets/images/large-logo.svg` |
| `mellat` | `mellat.svg` | `mellatbank.am/assets/images/logo.svg` |
| `vtb` | `vtb.svg` | header logo, inlined in the page's own markup |

### Two marks that are not the wordmark

`sil-insurance` and `fastbank` both ship a header wordmark that is pure white -
the white-on-dark version, invisible on the white cards these are painted on -
and neither site ships a dark or colour wordmark anywhere. Both fall back to
the company's own app icon, which is full colour on its own ground and suits
the round avatar these are rendered in anyway. `fastbank.png` is only 64x64,
the largest frame its favicon carries, so it is sharp at the size it is drawn
but has nothing in reserve above that.

### Where curl does not get through

`inecobank.am` answers a plain request with **403**, and `conversebank.am`
and `mellatbank.am` return a shell that fills itself in with JavaScript. All
three were read with a real browser instead (Playwright driving Chrome), which
they serve normally. `ingoarmenia.am` returned Cloudflare **522** and Nairi and
REGO sit behind a bot check that never clears; those three were supplied by
hand.

An organization with no file here keeps `logo` null and falls back to a
branded initials avatar, so the page works either way.

### A note on rego-insurance.png

It has no alpha channel: the white behind the wordmark is baked into the
pixels. That is fine everywhere it is painted today, all of which is white,
but it will show as a white block on any tinted or dark surface.

To add one later: drop `<slug>.<svg\|png>` in this directory, set `logo` on
that row in `OrganizationSeeder`, and add a backfill migration next to
`set_bank_logos_on_organizations_table` - the seeder only covers a fresh
install, because `firstOrCreate` leaves an existing row alone.

## Before adding an SVG

These are served from our own origin, so an SVG is same-origin active content.
Check it carries none before committing it:

    grep -iE '<script|on[a-z]+=|foreignObject|javascript:|<image|xlink:href="http' <file>

`<image` matters as much as `<script>`: AraratBank's own `ararat-mobile-logo.svg`
is a 68KB wrapper around a raster, which is why that one is a sprite crop.

Prefer the colour version over a monochrome one, and a version that reads on a
white ground - the avatars these sit in are white.
