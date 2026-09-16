<?php

namespace App\Services\Notifications;

use App\Mail\VisaRequestReceived;
use App\Models\VisaResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Tells a visa agency it has been asked to price a request. The token link is
 * the agency's way in, so this works whether or not it has a dashboard login.
 */
class VisaRequestMailer
{
    public static function notify(VisaResponse $response): void
    {
        $recipients = $response->organization->users()->pluck('email')->filter();

        if ($recipients->isEmpty()) {
            return;
        }

        $respondUrl = $response->secureRespondUrl();

        foreach ($recipients as $email) {
            Mail::to($email)->send(new VisaRequestReceived($response, $respondUrl));
        }
    }
}
