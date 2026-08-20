<?php

namespace App\Console\Commands;

use App\Models\QuoteRequest;
use Illuminate\Console\Command;

/**
 * Clears the children's ages off travel requests that are long finished.
 *
 * The ages exist for one reason: an agency prices a 2-year-old and a
 * 15-year-old very differently, so it needs them while it is quoting. Once
 * the request has closed and the offers have expired, nothing reads them
 * again - and they are personal data about a minor, so keeping them
 * indefinitely is retention without a purpose.
 *
 * Deliberately not a Prunable model: the request itself is part of the
 * traveller's own history and stays visible on "My Trips". Only this one
 * field goes, and the children *count* stays, so the record still reads
 * correctly ("2 adults, 1 child") afterwards.
 */
class PurgeExpiredTravelRequestDetails extends Command
{
    protected $signature = 'tourism:purge-expired-details';

    protected $description = "Clear children's ages from travel requests that expired long enough ago to have no further use";

    /**
     * Long enough after expiry that nobody is still acting on the request -
     * an agency chasing a late reply, a traveller reopening a conversation -
     * but not so long that the data sits around for no reason.
     */
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
