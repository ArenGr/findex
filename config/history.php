<?php

return [

    /*
    |--------------------------------------------------------------------------
    | History Retention
    |--------------------------------------------------------------------------
    |
    | currency_rate_history and mortgage_offer_history only grow on genuine
    | changes (not every scrape), so this isn't urgent at MVP1 volume - but
    | with no pruning at all, they'd grow unbounded indefinitely. Records
    | older than this many months are pruned by the scheduled `model:prune`
    | command (see CurrencyRateHistory/MortgageOfferHistory's Prunable
    | implementation and bootstrap/app.php's schedule).
    |
    */

    'retention_months' => (int) env('HISTORY_RETENTION_MONTHS', 24),

];
