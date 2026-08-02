<?php

namespace App\Services\Notifications;

use App\Models\ExchangeQuoteResponse;

/**
 * Abstracts "tell this exchange office about a currency exchange quote
 * request" away from any one channel - same reasoning as
 * PartnerNotifierInterface for the travel flow, just a distinct interface
 * since the two domains have no shared payload shape.
 */
interface ExchangeNotifierInterface
{
    /**
     * Notify the organization that owns this already-created, pending
     * response. Returns true if the notification was actually delivered -
     * the caller logs a warning on false, but the response row (and its
     * secure respond link) exists either way, so the partner isn't
     * permanently unreachable just because one notification attempt failed.
     */
    public function notify(ExchangeQuoteResponse $response): bool;

    /**
     * A one-time nudge for a response still pending after a while (see
     * RemindExchangePartnersOfPendingQuotes) - a distinct, shorter message
     * from notify()'s initial request rather than resending the same one.
     */
    public function remind(ExchangeQuoteResponse $response): bool;
}
