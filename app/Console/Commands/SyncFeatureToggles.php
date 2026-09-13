<?php

namespace App\Console\Commands;

use App\Models\FeatureToggle;
use App\Support\Features;
use Illuminate\Console\Command;

class SyncFeatureToggles extends Command
{
    protected $signature = 'features:sync {--reset : Also set every flag back to its default}';

    protected $description = 'Add a feature_toggles row for every flag declared in App\Support\Features';

    public function handle(): int
    {
        $added = FeatureToggle::sync();
        $this->info($added === 0 ? 'Already in sync.' : "Added {$added} feature toggle(s).");

        if ($this->option('reset')) {
            $this->info('Reset '.FeatureToggle::resetToDefaults().' flag(s) to their default.');
        }

        $states = Features::states();

        $this->table(
            ['Group', 'Feature', 'Key', 'State'],
            collect(Features::all())->map(fn (array $meta, string $key) => [
                $meta['group'],
                ($meta['parent'] ? '  ' : '').$meta['label'],
                $key,
                $states[$key] ? 'on' : 'off',
            ])->values()->all()
        );

        return self::SUCCESS;
    }
}
