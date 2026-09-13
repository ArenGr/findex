<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use Illuminate\Contracts\Cache\Repository as Cache;

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

    // The pricing inputs, normalised then hashed.
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
