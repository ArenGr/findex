<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

// The organizations a request goes out to, shown as a "trusted by" strip.
class TravelPartners
{
    public const MIN_TO_SHOW = 1;

    private const LIMIT = 8;

    /** Words that add length to a strip without telling anyone apart. */
    private const NOISE = ['insurance company', 'insurance', 'travel agency', 'travel', 'bank', 'armenia', 'cjsc', 'llc', 'ltd'];

    /** @return Collection<int, object{name: string, slug: string, short: string, logo: ?string, ratio: float, initial: string}> */
    public static function all(): Collection
    {
        return self::forType('tourism');
    }

    /** @return Collection<int, object{name: string, slug: string, short: string, logo: ?string, ratio: float, initial: string}> */
    public static function forType(string $type, int $limit = self::LIMIT): Collection
    {
        $rows = Cache::remember(
            "partners.{$type}.{$limit}",
            now()->addHour(),
            fn () => Organization::query()
                ->where('type', $type)
                ->active()
                ->orderBy('name')
                ->limit($limit)
                ->get(['id', 'name', 'slug', 'logo'])
                ->map(fn (Organization $org) => [
                    'name' => $org->name,
                    'slug' => $org->slug,
                    'short' => self::short($org->name),
                    'logo' => $org->logo,
                    'ratio' => self::ratio($org->logo),
                    'initial' => mb_strtoupper(mb_substr($org->name, 0, 1)),
                ])
                ->all()
        );

        return collect($rows)->map(fn (array $row) => (object) $row);
    }

    public static function short(string $name): string
    {
        // "Armenia Insurance" is all noise; keep the place name before giving up.
        foreach ([self::NOISE, array_diff(self::NOISE, ['armenia'])] as $noise) {
            $short = preg_replace('/\b('.implode('|', array_map('preg_quote', $noise)).')\b/iu', '', $name);
            $short = preg_replace('/\(\s*\)/u', '', $short);
            $short = trim(preg_replace('/\s{2,}/u', ' ', $short), ' -,.');

            if ($short !== '') {
                return $short;
            }
        }

        return $name;
    }

    /** Width over height of a logo file, 1.0 when it cannot be read. */
    public static function ratio(?string $logo): float
    {
        $path = $logo ? public_path(ltrim(parse_url($logo, PHP_URL_PATH) ?? '', '/')) : null;

        if (! $path || ! is_file($path)) {
            return 1.0;
        }

        if (str_ends_with(strtolower($path), '.svg')) {
            $head = (string) file_get_contents($path, false, null, 0, 4096);

            if (preg_match('/viewBox="\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)/i', $head, $m) && (float) $m[2] > 0) {
                return round((float) $m[1] / (float) $m[2], 3);
            }
            if (preg_match('/\swidth="([\d.]+)/i', $head, $w) && preg_match('/\sheight="([\d.]+)/i', $head, $h) && (float) $h[1] > 0) {
                return round((float) $w[1] / (float) $h[1], 3);
            }

            return 1.0;
        }

        $size = @getimagesize($path);

        return $size && $size[1] > 0 ? round($size[0] / $size[1], 3) : 1.0;
    }
}
