<?php

namespace App\Jobs;

use App\Mail\DestinationNowAvailable;
use App\Models\DestinationAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Fired when a tourism org starts serving a destination (see
 * TourismController::updateDestinations()) - a no-op if nobody had an
 * alert set for that country. One-shot: alerts for this country are
 * cleared after sending, matching the "notify me once available"
 * expectation rather than repeating on every future partner.
 */
class NotifyDestinationAlertsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $countryCode) {}

    public function handle(): void
    {
        $alerts = DestinationAlert::where('destination_country', $this->countryCode)->get();

        foreach ($alerts as $alert) {
            $unsubscribeUrl = URL::signedRoute('tourism.destination-alerts.unsubscribe', [
                'locale' => $alert->locale,
                'email' => $alert->email,
            ]);

            Mail::to($alert->email)
                ->locale($alert->locale)
                ->send(new DestinationNowAvailable($alert->destination_country, $alert->user?->name, $unsubscribeUrl));
        }

        DestinationAlert::where('destination_country', $this->countryCode)->delete();
    }
}
