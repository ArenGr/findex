<?php

namespace App\Services\Notifications;

use App\Mail\TravelRequestReceived;
use App\Models\QuoteResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Emails an agency's dashboard accounts about a travel request it has been
 * sent - the fallback used when the Telegram push didn't happen (see
 * TravelRequestReceived for when that is, and why it's now normal).
 *
 * Deliberately not another PartnerNotifierInterface implementation: that
 * interface is the primary channel, chosen once and bound in
 * AppServiceProvider, and this is a fallback for when the primary one
 * reports it couldn't deliver. Making it a second binding would mean
 * something had to decide between them on every call.
 */
class AgencyRequestMailer
{
    public static function notify(QuoteResponse $response): void
    {
        $recipients = $response->organization->users()->pluck('email')->filter();

        if ($recipients->isEmpty()) {
            return;
        }

        // The inbox, not a signed per-request link: an agency user signs in,
        // so the request is already reachable from the dashboard, and a link
        // that works without signing in would be a credential in an inbox we
        // don't control.
        $inboxUrl = route('org.dashboard.travel-requests.show', [
            'locale' => $response->quoteRequest->locale,
            'response' => $response->id,
        ]);

        foreach ($recipients as $email) {
            Mail::to($email)->send(new TravelRequestReceived($response, $inboxUrl));
        }
    }
}
