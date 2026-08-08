<?php

namespace Database\Seeders;

use App\Http\Controllers\OfferController;
use App\Models\FeatureToggle;
use Illuminate\Database\Seeder;

/**
 * One row per bank product page the app can render. The panel only flips
 * these - it can't create or delete them - so this seeder is what keeps the
 * toggle list in step with OfferController::CATEGORIES.
 *
 * firstOrCreate, not updateOrCreate: re-running a seed must never silently
 * switch a category back off (or on) behind an admin who set it
 * deliberately. Only genuinely new keys get a default.
 */
class FeatureToggleSeeder extends Seeder
{
    /**
     * On by default - these have real pages with real data behind them.
     * Everything else starts off and is enabled from the panel once its
     * page is ready to show.
     */
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
