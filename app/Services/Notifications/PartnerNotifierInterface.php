<?php

namespace App\Services\Notifications;

use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;

interface PartnerNotifierInterface
{
    // Notify the organization that owns this already-created, pending quote response.
    public function notify(QuoteResponse $response): bool;

    public function remind(QuoteResponse $response): bool;

    public function notifyClaim(QuoteSuggestion $suggestion): bool;
}
