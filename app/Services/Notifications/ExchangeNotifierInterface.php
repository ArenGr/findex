<?php

namespace App\Services\Notifications;

use App\Models\ExchangeQuoteResponse;

interface ExchangeNotifierInterface
{
    // Notify the organization that owns this already-created, pending response.
    public function notify(ExchangeQuoteResponse $response): bool;

    public function remind(ExchangeQuoteResponse $response): bool;

    public function notifyAccepted(ExchangeQuoteResponse $response): bool;
}
