<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Caches successful premium sets so repeated identical requests do not each
 * hit the upstream calculator.
 *
 * Sil is the sole source of these quotes, so it is the one thing worth
 * shielding from avoidable volume - a page refresh, a retry after a transient
 * error, two people asking about the same car. This wraps the real source
 * (SilMarketQuoteSource) and returns a stored answer where one exists.
 *
 * The cache key is a HASH of the pricing inputs, never the inputs themselves.
 * The plate and the owner's ID go into a sha256 and only the digest is used
 * as the key; the stored value is the list of premiums, which is not
 * sensitive. So caching does not reintroduce the very thing the rest of this
 * namespace is arranged to avoid - a plate or an ID number sitting at rest.
 * The phone, email and bank account are deliberately NOT part of the key:
 * they do not change the premium (they are only what Sil demands before it
 * will answer), so two people with the same vehicle share a hit regardless.
 *
 * Only non-empty results are cached. An empty result usually means Sil
 * wobbled, and caching that would serve "no quotes" for the whole window; a
 * miss next time gives it another chance. Exceptions are never cached either
 * - they propagate untouched, so a rejected ID is re-checked every time
 * rather than remembered.
 */
class CachedMarketQuoteSource implements MarketQuoteSourceInterface
{
    private const KEY_PREFIX = 'insurance:quote:v1:';

    public function __construct(
        private readonly MarketQuoteSourceInterface $inner,
        private readonly Cache $cache,
    ) {}

    public function premiums(
        AutoInsuranceRequest $request,
        QuoteIdentity $identity,
        MarketQuoteDetails $details,
    ): array {
        $ttl = (int) config('insurance.quote_cache_ttl', 0);

        if ($ttl <= 0) {
            // Caching switched off - straight through, every time.
            return $this->inner->premiums($request, $identity, $details);
        }

        $key = $this->cacheKey($request, $identity);

        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return $cached;
        }

        // A rejected ID/bank/email throws out of here and is never cached.
        $premiums = $this->inner->premiums($request, $identity, $details);

        if ($premiums !== []) {
            $this->cache->put($key, $premiums, $ttl);
        }

        return $premiums;
    }

    /**
     * The pricing inputs, normalised then hashed. Only the digest leaves this
     * method - the raw plate and ID never reach the cache.
     */
    private function cacheKey(AutoInsuranceRequest $request, QuoteIdentity $identity): string
    {
        $fingerprint = implode('|', [
            mb_strtoupper(trim($identity->plateNumber)),
            mb_strtoupper(trim($identity->idNumber)),
            $request->contract_term_months,
        ]);

        return self::KEY_PREFIX.hash('sha256', $fingerprint);
    }
}
