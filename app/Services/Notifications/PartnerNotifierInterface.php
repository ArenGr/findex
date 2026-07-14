<?php

namespace App\Services\Notifications;

use App\Models\QuoteResponse;
use App\Models\QuoteSuggestion;

/**
 * Abstracts "tell this partner about a quote request" away from any one
 * channel, so SendQuoteRequestToPartnersJob (the business logic - matching
 * partners, creating the pending QuoteResponse row) never has to change
 * when a new channel (WhatsApp, email, SMS) is added - only a new
 * implementation of this interface, bound alongside or instead of
 * TelegramPartnerNotifier in AppServiceProvider.
 */
interface PartnerNotifierInterface
{
    /**
     * Notify the organization that owns this already-created, pending quote
     * response. Returns true if the notification was actually delivered -
     * the caller logs a warning on false, but the response row (and its
     * secure respond link) exists either way, so the partner isn't
     * permanently unreachable just because one notification attempt failed.
     */
    public function notify(QuoteResponse $response): bool;

    /**
     * A one-time nudge for a response still pending after a while (see
     * RemindPartnersOfPendingQuotes) - a distinct, shorter message from
     * notify()'s initial request rather than resending the same one.
     */
    public function remind(QuoteResponse $response): bool;

    /**
     * A customer has just claimed this suggestion's promo code (see
     * QuoteRequestController::claimSuggestion) - tells the org who to
     * expect so they can verify the same account holder when the code is
     * redeemed in person.
     */
    public function notifyClaim(QuoteSuggestion $suggestion): bool;
}
