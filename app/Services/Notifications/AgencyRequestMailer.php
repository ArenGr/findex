<?php

namespace App\Services\Notifications;

use App\Mail\TravelRequestReceived;
use App\Models\QuoteResponse;
use Illuminate\Support\Facades\Mail;

class AgencyRequestMailer
{
    public static function notify(QuoteResponse $response): void
    {
        $recipients = $response->organization->users()->pluck('email')->filter();

        if ($recipients->isEmpty()) {
            return;
        }

        $inboxUrl = route('org.dashboard.travel-requests.show', [
            'locale' => $response->quoteRequest->locale,
            'response' => $response->id,
        ]);

        foreach ($recipients as $email) {
            Mail::to($email)->send(new TravelRequestReceived($response, $inboxUrl));
        }
    }
}
