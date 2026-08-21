<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Parsers\BranchParserFactory;
use App\Services\BranchScraper;
use Illuminate\Console\Command;

class ScrapeBranches extends Command
{
    protected $signature = 'scrape:branches {--organization= : Organization slug to scrape}';

    protected $description = 'Scrape branch locations, addresses and opening hours from organizations';

    public function handle(BranchScraper $scraper, BranchParserFactory $parsers): int
    {
        $slug = $this->option('organization');

        $organizations = Organization::query()
            ->active()
            ->when($slug, fn ($query) => $query->where('slug', $slug))
            ->whereHas('sources', fn ($query) => $query
                ->where('source_type', BranchScraper::SOURCE_TYPE)
                ->where('is_active', true))
            ->orderBy('slug')
            ->get();

        if ($organizations->isEmpty()) {
            $this->error($slug
                ? "No active branch source for '{$slug}'."
                : 'No organizations have an active branch source.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($organizations as $organization) {
            if (! $parsers->supports($organization)) {
                $this->warn("  {$organization->slug}: no branch parser configured, skipped");

                continue;
            }

            $job = $scraper->scrape($organization);

            if ($job->status === 'success') {
                $this->info(sprintf('  %-14s %d branches', $organization->slug, $job->records_found));
            } else {
                $failed++;
                $this->error(sprintf('  %-14s %s', $organization->slug, $job->error_message));
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
