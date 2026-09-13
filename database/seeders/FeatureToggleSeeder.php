<?php

namespace Database\Seeders;

use App\Http\Controllers\OfferController;
use App\Models\FeatureToggle;
use Illuminate\Database\Seeder;

// One row per bank product page the app can render.
class FeatureToggleSeeder extends Seeder
{
    // On by default - these have real pages with real data behind them.
    private const ENABLED_BY_DEFAULT = [
        'mortgages',
        'personal-loans',
        'banking',
    ];

    public function run(): void
    {
        foreach (OfferController::CATEGORIES as $key) {
            FeatureToggle::firstOrCreate(
                ['key' => $key],
                ['is_enabled' => in_array($key, self::ENABLED_BY_DEFAULT, true)],
            );
        }

        FeatureToggle::forgetCache();

        $this->command?->info('Feature toggles seeded for '.count(OfferController::CATEGORIES).' bank product pages.');
    }
}
