<?php

namespace App\Support;

class TravelHero
{
    private const WIDTHS = [
        'hero-photo' => [900, 1420],
        'hero-mobile' => [640, 900, 1080],
        'trip-empty' => [320, 640, 960],
        'panorama' => [960, 1440, 1916],
    ];

    private const FORMATS = ['avif', 'webp'];
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    /**
     * @return array{srcset: array<string, string>, src: string, width: int, height: int}|null
     */
    public static function asset(string $name): ?array
    {
        if (! isset(self::WIDTHS[$name])) {
            return null;
        }

        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name];
        }

        $srcset = [];

        foreach (self::FORMATS as $format) {
            $entries = [];

            foreach (self::WIDTHS[$name] as $width) {
                $path = "images/travel/{$name}-{$width}.{$format}";

                if (is_file(public_path($path))) {
                    $entries[] = asset($path)." {$width}w";
                }
            }

            if ($entries !== []) {
                $srcset[$format] = implode(', ', $entries);
            }
        }

        if (!isset($srcset['webp'])) {
            return self::$cache[$name] = null;
        }

        $fallbackWidth = self::largestAvailable($name);
        $fallback = "images/travel/{$name}-{$fallbackWidth}.webp";
        $size = @getimagesize(public_path($fallback));

        return self::$cache[$name] = [
            'srcset' => $srcset,
            'src' => asset($fallback),
            'width' => $size[0] ?? $fallbackWidth,
            'height' => $size[1] ?? 0,
        ];
    }

    private static function largestAvailable(string $name): int
    {
        $found = array_filter(
            self::WIDTHS[$name],
            fn (int $width) => is_file(public_path("images/travel/{$name}-{$width}.webp"))
        );

        return (int) max($found);
    }
}
