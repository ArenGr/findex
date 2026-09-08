<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TravelPartners
{
    public const MIN_TO_SHOW = 1;
    private const LIMIT = 8;

    /** @return Collection<int, object{name: string, logo: ?string, initial: string}> */
    public static function all(): Collection
    {
        $rows = Cache::remember(
            'travel.partners',
            now()->addHour(),
            fn () => Organization::query()
                ->where('type', 'tourism')
                ->active()
                ->orderBy('name')
                ->limit(self::LIMIT)
                ->get(['id', 'name', 'logo'])
                ->map(fn (Organization $org) => [
                    'name' => $org->name,
                    'logo' => $org->logo,
                    'initial' => mb_strtoupper(mb_substr($org->name, 0, 1)),
                ])
                ->all()
        );
        return collect($rows)->map(fn (array $row) => (object) $row);
    }
}
