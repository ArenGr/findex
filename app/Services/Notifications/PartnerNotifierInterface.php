<?php

namespace App\Services\Notifications;

use App\Models\QuoteResponse;

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
}
