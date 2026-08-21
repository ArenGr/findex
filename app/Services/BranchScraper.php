<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\ScrapingJob;
use App\Parsers\BranchParserFactory;
use Illuminate\Support\Facades\DB;

class BranchScraper
{
    public const SOURCE_TYPE = 'branches';

    /**
     * Below this, a listing that came back short is treated as a broken
     * parser rather than a bank that closed everything overnight, and the
     * retire-the-missing step is skipped. Banks here run between 3 and 60
     * branches, so a scrape returning a single row is far more likely to be
     * a markup change than real news.
     */
    private const MIN_BRANCHES_TO_RETIRE_OTHERS = 3;

    public function __construct(
        private BranchParserFactory $parsers,
        private ScraperHttpClient $http,
    ) {}

    public function scrape(Organization $organization): ScrapingJob
    {
        $job = ScrapingJob::updateOrCreate(
            ['organization_id' => $organization->id, 'source_type' => self::SOURCE_TYPE],
            ['status' => 'pending', 'started_at' => null, 'finished_at' => null, 'records_found' => 0, 'error_message' => null],
        );
        $job->logs()->delete();

        try {
            $job->markAsRunning();
            $job->log('info', "Starting to scrape branches for {$organization->name}");

            $source = $organization->sources()
                ->where('source_type', self::SOURCE_TYPE)
                ->where('is_active', true)
                ->first();

            if (! $source) {
                throw new \RuntimeException("Source 'branches' not found for {$organization->name}");
            }

            $url = $source->getFullUrl();
            $job->log('info', "Fetching from: {$url}");

            $branches = $this->parsers->for($organization)->parse($this->http->get($url, $source->request_headers ?? []));

            $saved = $this->save($organization, $branches, $job);

            $job->log('info', "Successfully parsed {$saved} branches");

            if ($saved === 0) {
                $job->log('warning', 'Zero branches parsed - the source markup may have changed');
                AdminNotifier::zeroRecordsScraped($organization->name, self::SOURCE_TYPE);
            }

            $source->markAsScraped();
            $job->markAsSuccess($saved);

            return $job;
        } catch (\Throwable $e) {
            $job->log('error', $e->getMessage(), ['exception' => get_class($e)]);
            $job->markAsFailed($e->getMessage());

            return $job;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $branches
     */
    private function save(Organization $organization, array $branches, ScrapingJob $job): int
    {
        $seen = [];

        DB::transaction(function () use ($organization, $branches, &$seen, $job) {
            foreach ($branches as $branch) {
                $address = trim((string) ($branch['address'] ?? ''));

                if ($address === '') {
                    continue;
                }

                // Keyed on the address rather than the name: banks rename
                // branches ("Avan" becoming "Avan 2") far more readily than
                // they move them, and keying on the name would leave the old
                // row behind as a duplicate of a branch that still exists.
                $record = Branch::updateOrCreate(
                    ['organization_id' => $organization->id, 'address' => $address],
                    [
                        'name' => trim((string) ($branch['name'] ?? '')) ?: $address,
                        'city' => $branch['city'] ?? null,
                        'latitude' => $branch['latitude'] ?? null,
                        'longitude' => $branch['longitude'] ?? null,
                        'opening_hours' => $branch['opening_hours'] ?? null,
                        'is_active' => true,
                    ],
                );

                $seen[] = $record->id;
            }

            $this->retireMissing($organization, $seen, $job);
        });

        return count($seen);
    }

    /**
     * A branch the bank has stopped listing has almost certainly closed. It
     * is deactivated rather than deleted: reviews and any history hang off
     * it, and a branch that vanishes from the table takes them with it.
     *
     * @param  array<int, int>  $seen
     */
    private function retireMissing(Organization $organization, array $seen, ScrapingJob $job): void
    {
        if (count($seen) < self::MIN_BRANCHES_TO_RETIRE_OTHERS) {
            $job->log('warning', sprintf(
                'Only %d branches parsed - leaving the existing ones alone rather than retiring them on a likely-broken read.',
                count($seen),
            ));

            return;
        }

        $retired = Branch::where('organization_id', $organization->id)
            ->whereNotIn('id', $seen)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        if ($retired > 0) {
            $job->log('info', "Deactivated {$retired} branch(es) the bank no longer lists");
        }
    }
}
