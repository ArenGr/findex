#!/usr/bin/env python3
"""
Splits the self-hosted FreeSans into per-script WOFF2 subsets.

FreeSans ships as one file carrying every script it supports - Latin,
Cyrillic, Armenian, Greek, Hebrew, Japanese, Devanagari and more - which is
why it is ~109 KB. It is the body face, so every visitor downloaded all of
it to render, typically, a few hundred Latin characters.

The Bunny-hosted families (Montserrat, Manrope) already arrive split this
way; FreeSans missed out only because it is self-hosted and nothing split
it. This does that job, so the browser fetches just the ranges it needs to
paint - the unicode-range in resources/css/fonts-freesans.css decides which.

Run after replacing either source file:

    pip install fonttools brotli
    python3 tools/subset-freesans.py

The generated files are committed, so production needs neither Python nor
fonttools.
"""
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SOURCES = {"400": ROOT / "public/fonts/FreeSans.woff2", "700": ROOT / "public/fonts/FreeSansBold.woff2"}
OUT = ROOT / "public/fonts/subset"

# Kept byte-identical to the unicode-range values in
# resources/css/fonts-freesans.css - if these two ever disagree, the browser
# asks for a subset that does not contain the glyph and the character falls
# back to another font.
SUBSETS = {
    "latin": "U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,"
             "U+0304,U+0308,U+0329,U+2000-206F,U+2074,U+20AC,U+2122,U+2190-2193,"
             "U+2212,U+2215,U+2605,U+2713,U+FEFF,U+FFFD",
    "latin-ext": "U+0100-024F,U+0259,U+1E00-1EFF,U+2020,U+20A0-20AB,U+20AD-20CF,U+2C60-2C7F,U+A720-A7FF",
    "cyrillic": "U+0301,U+0400-045F,U+0490-0491,U+04B0-04B1,U+2116",
    "cyrillic-ext": "U+0460-052F,U+1C80-1C88,U+20B4,U+2DE0-2DFF,U+A640-A69F,U+FE2E-FE2F",
    "armenian": "U+0530-058F,U+FB13-FB17",
}


def main() -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    total_before = total_after = 0

    for weight, source in SOURCES.items():
        if not source.exists():
            print(f"missing source: {source}", file=sys.stderr)
            return 1

        total_before += source.stat().st_size

        for name, unicodes in SUBSETS.items():
            # FreeSansBold carries no Armenian glyphs at all, so a bold
            # Armenian subset would be an empty file the browser fetches and
            # then falls back from anyway. (Bold Armenian already rendered
            # from a system font before any of this.)
            if weight == "700" and name == "armenian":
                continue

            target = OUT / f"freesans-{weight}-{name}.woff2"
            subprocess.run(
                [
                    sys.executable, "-m", "fontTools.subset", str(source),
                    f"--unicodes={unicodes}",
                    "--flavor=woff2",
                    "--layout-features=*",
                    "--name-IDs=*",
                    f"--output-file={target}",
                ],
                check=True,
                stdout=subprocess.DEVNULL,
            )
            total_after += target.stat().st_size
            print(f"  freesans-{weight}-{name:<13} {target.stat().st_size / 1024:6.1f} KB")

    print(f"\n  sources {total_before / 1024:.0f} KB -> subsets {total_after / 1024:.0f} KB")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
