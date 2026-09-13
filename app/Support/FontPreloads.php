<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

// The font files a page needs before it can paint its text correctly.
class FontPreloads
{
    // The variants worth preloading, per family.
    private const VARIANTS = [
        'montserrat' => ['600:normal', '700:normal'],
        'plus-jakarta-sans' => ['400:normal', '500:normal', '600:normal', '700:normal', '800:normal'],
    ];

    // Representative code points per script, used to pick the right subset out of the variant's files.
    private const SCRIPTS = [
        'latin' => 'U+0000-00FF',
        'ru' => 'U+0400',
    ];

    /**
     * URLs of the files this family and locale should preload.
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
     * Build-relative paths, which is all that is safe to cache: they do not depend on the request.
     *
     * @return list<string>
     */
    private static function paths(string $family, string $locale): array
    {
        $manifestPath = public_path('build/fonts-manifest.json');

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
