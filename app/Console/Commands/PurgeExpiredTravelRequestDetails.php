<?php

namespace App\Console\Commands;

use App\Models\QuoteRequest;
use Illuminate\Console\Command;

// Clears the children's ages off travel requests that are long finished.
class PurgeExpiredTravelRequestDetails extends Command
{
    protected $signature = 'tourism:purge-expired-details';

    protected $description = "Clear children's ages from travel requests that expired long enough ago to have no further use";

    private const GRACE_DAYS = 30;

    public function handle(): int
    {
        $cutoff = now()->subDays(self::GRACE_DAYS);

        $purged = QuoteRequest::query()
            ->whereNotNull('child_ages')
            ->where('expires_at', '<=', $cutoff)
            ->update(['child_ages' => null]);

        $this->info("Cleared children's ages from {$purged} expired travel request(s).");

        return self::SUCCESS;
    }
}
