<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Quote cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Auto insurance premiums are priced live by a single upstream (Sil's
    | Bureau calculator - see App\Services\Insurance\SilMarketQuoteSource).
    | That makes it the one thing worth protecting from repeated calls: a
    | refresh, a retry after a transient error, or two people asking about the
    | same vehicle should not each cost a fresh pair of requests.
    |
    | So a successful premium set is cached, keyed on a *hash* of the pricing
    | inputs (plate, ID, term) - never the raw values. Compulsory motor TPL
    | tariffs do not move minute to minute, so a short window is invisible to
    | users while cutting call volume to roughly one per distinct vehicle.
    |
    | Set to 0 to disable caching entirely (every request calls upstream).
    |
    */
    'quote_cache_ttl' => (int) env('INSURANCE_QUOTE_CACHE_TTL', 1800),
];
