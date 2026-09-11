<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The font files a page needs before it can paint its text correctly.
 *
 * FreeSans and Allerta Stencil are preloaded by hand in layouts/app.blade.php
 * because they live in public/ under stable names. The Bunny families do not:
 * they are downloaded by laravel-vite-plugin into content-hashed build assets,
 * so the only way to name the file is to look it up in the font manifest.
 *
 * It has to be preloaded. A face the browser only discovers when it reaches
 * the heading is fetched too late to make the first frame, so every h1 and h2
 * painted in the fallback and then visibly re-rendered once Montserrat landed.
 * The plugin's own `preload: true` is no use here - it preloads every subset
 * of the weight, Cyrillic included on English pages - so this picks just the
 * scripts the current locale can actually paint.
 */
class FontPreloads
{
    /**
     * The variants worth preloading, per family.
     *
     * Montserrat needs both weights `font-heading` is used at. 700 alone was
     * not enough: `font-semibold` headings are Montserrat 600, and they are
     * everywhere - the asides on the insurance and travel request pages are
     * built from them. A weight that is not preloaded is only discovered when
     * the browser reaches the heading, which is too late for the first frame,
     * so those headings painted in the fallback and then re-rendered.
     *
     * Plus Jakarta Sans needs all five: the travel request page sets
     * font-jakarta on its wrapper, so it is that page's body face at every
     * weight, including the 800 its headlines are set in. It is not in the
     * layout's preloads - only that page pushes it, because nowhere else uses
     * it.
     */
    private const VARIANTS = [
        'montserrat' => ['600:normal', '700:normal'],
        'plus-jakarta-sans' => ['400:normal', '500:normal', '600:normal', '700:normal', '800:normal'],
    ];

    /**
     * Representative code points per script, used to pick the right subset out
     * of the variant's files. Latin is always needed - digits, punctuation and
     * bank names appear in every locale - plus the script the page is in.
     * Armenian is absent deliberately: Montserrat has no Armenian glyphs, so
     * Armenian headings render from the system's Armenian face instead.
     */
    private const SCRIPTS = [
        'latin' => 'U+0000-00FF',
        'ru' => 'U+0400',
    ];

    /**
     * URLs of the files this family and locale should preload.
     *
     * asset() is applied here, on every request, and never cached: it resolves
     * against the host the visitor actually used. Caching its output froze one
     * host into the cache - populated from 127.0.0.1, served to someone on
     * localhost - which made every preload cross-origin. Fonts are always
     * fetched in CORS mode, so the browser blocked them outright, and the face
     * then arrived late through the stylesheet's own relative URL, which is a
     * visible swap on every heading.
     *
     * @return list<string>
     */
    public static function urls(string $family, string $locale): array
    {
        return array_map(
            fn (string $path) => asset($path),
            self::paths($family, $locale)
        );
    }

    /**
     * Build-relative paths, which is all that is safe to cache: they do not
     * depend on the request.
     *
     * @return list<string>
     */
    private static function paths(string $family, string $locale): array
    {
        $manifestPath = public_path('build/fonts-manifest.json');

        // Keyed by the manifest's mtime as well as the locale: every build
        // rewrites these filenames with fresh content hashes, and a cache that
        // outlived a deploy would preload files that 404.
        $stamp = is_file($manifestPath) ? filemtime($manifestPath) : 0;

        return Cache::rememberForever(
            "fonts.preload.{$family}.{$locale}.{$stamp}.paths",
            fn () => self::resolve($family, $locale)
        );
    }

    /**
     * @return list<string>
     */
    private static function resolve(string $family, string $locale): array
    {
        $manifestPath = public_path('build/fonts-manifest.json');

        // No build yet (a fresh checkout, or `npm run dev`): preloading is an
        // optimisation, so skip it rather than break the page.
        if (! is_file($manifestPath)) {
            return [];
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $variants = $manifest['families'][$family]['variants'] ?? [];

        $wanted = array_filter([
            self::SCRIPTS['latin'],
            self::SCRIPTS[$locale] ?? null,
        ]);

        $urls = [];

        foreach (self::VARIANTS[$family] ?? [] as $variant) {
            foreach ($variants[$variant]['files'] ?? [] as $file) {
                if (($file['format'] ?? null) !== 'woff2') {
                    continue;
                }

                foreach ($wanted as $codePoint) {
                    if (str_contains($file['unicodeRange'] ?? '', $codePoint)) {
                        $urls[] = 'build/'.$file['file'];
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($urls));
    }
}
