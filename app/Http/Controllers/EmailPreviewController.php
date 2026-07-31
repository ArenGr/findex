<?php

namespace App\Http\Controllers;

use App\Mail\AutoInsuranceQuoteInterest;
use App\Mail\DestinationNowAvailable;
use App\Mail\QuoteRequestLinkResent;
use App\Mail\QuoteRequestSubmitted;
use App\Mail\QuoteResponseReceived;
use App\Mail\RateAlertTriggered;
use App\Mail\TripReviewPrompt;
use App\Mail\VerifyEmailAddress;
use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use App\Models\RateAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Dev-only visual preview for transactional emails - lets us eyeball the
 * shared layout/copy in a real browser without triggering a send or hunting
 * through an inbox. Every model here is built unpersisted (new Model([...])
 * + setRelation()) specifically so this never touches the database.
 */
class EmailPreviewController extends Controller
{
    public function index(string $locale)
    {
        $templates = [
            'verify-email' => 'Verify email address',
            'destination-now-available' => 'Destination now available',
            'trip-review-prompt' => 'Trip review prompt',
            'quote-request-submitted' => 'Quote request submitted',
            'auto-insurance-quote-interest' => 'Auto insurance quote interest',
            'rate-alert-triggered' => 'Rate alert triggered',
            'quote-request-link-resent' => 'Quote request link resent',
            'quote-response-received' => 'Quote response received',
        ];

        return view('email-preview.index', ['templates' => $templates]);
    }

    public function show(string $locale, string $template)
    {
        $mailable = match ($template) {
            'verify-email' => $this->verifyEmail(),
            'destination-now-available' => $this->destinationNowAvailable(),
            'trip-review-prompt' => $this->tripReviewPrompt(),
            'quote-request-submitted' => $this->quoteRequestSubmitted(),
            'auto-insurance-quote-interest' => $this->autoInsuranceQuoteInterest(),
            'rate-alert-triggered' => $this->rateAlertTriggered(),
            'quote-request-link-resent' => $this->quoteRequestLinkResent(),
            'quote-response-received' => $this->quoteResponseReceived(),
            default => abort(404),
        };

        return $mailable;
    }

    private function organization(string $name = 'Ameria Bank', string $slug = 'ameria-bank'): Organization
    {
        return new Organization([
            'name' => $name,
            'slug' => $slug,
            'type' => 'bank',
        ]);
    }

    private function verifyEmail(): VerifyEmailAddress
    {
        $user = new User(['name' => 'Anna Grigoryan', 'email' => 'anna@example.com']);
        $user->id = 1;

        return new VerifyEmailAddress($user, URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'locale' => app()->getLocale(),
            'id' => 1,
            'hash' => sha1('anna@example.com'),
        ]));
    }

    private function destinationNowAvailable(): DestinationNowAvailable
    {
        $unsubscribeUrl = URL::signedRoute('tourism.destination-alerts.unsubscribe', [
            'locale' => app()->getLocale(),
            'email' => 'anna@example.com',
        ]);

        return new DestinationNowAvailable('AE', 'Anna Grigoryan', $unsubscribeUrl);
    }

    private function tripReviewPrompt(): TripReviewPrompt
    {
        $user = new User(['name' => 'Anna Grigoryan', 'email' => 'anna@example.com']);
        $user->id = 1;

        $quoteRequest = new QuoteRequest([
            'destination_country' => 'GE',
            'locale' => 'en',
        ]);
        $quoteRequest->id = 1;
        $quoteRequest->setRelation('user', $user);

        $organizations = new Collection([
            $this->organization('Sunny Travel', 'sunny-travel'),
            $this->organization('Yerevan Tours', 'yerevan-tours'),
        ]);

        return new TripReviewPrompt($quoteRequest, $organizations);
    }

    private function quoteRequestSubmitted(): QuoteRequestSubmitted
    {
        $quoteRequest = new QuoteRequest(['destination_country' => 'TH', 'locale' => 'en', 'guest_name' => 'Davit Sargsyan']);
        $quoteRequest->id = 1;
        $quoteRequest->setRelation('user', null);

        return new QuoteRequestSubmitted($quoteRequest, url('/en/tourism/requests/1'), 4);
    }

    private function autoInsuranceQuoteInterest(): AutoInsuranceQuoteInterest
    {
        $request = new AutoInsuranceRequest([
            'guest_name' => 'Davit Sargsyan',
            'guest_email' => 'davit@example.com',
            'vehicle_plate' => '01AA123',
        ]);
        $request->id = 1;

        $quote = new AutoInsuranceQuote([
            'status' => AutoInsuranceQuote::STATUS_QUOTED,
            'premium_amount' => '45000.00',
            'premium_currency' => 'AMD',
        ]);
        $quote->id = 1;
        $quote->setRelation('autoInsuranceRequest', $request);
        $quote->setRelation('organization', $this->organization('Ameria Bank', 'ameria-bank'));

        return new AutoInsuranceQuoteInterest($quote);
    }

    private function rateAlertTriggered(): RateAlertTriggered
    {
        $currency = new Currency(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$']);
        $currency->id = 1;

        $organization = $this->organization();
        $organization->id = 1;

        $user = new User(['name' => 'Anna Grigoryan', 'email' => 'anna@example.com']);
        $user->id = 1;

        $alert = new RateAlert([
            'rate_field' => 'buy_rate',
            'direction' => 'above',
            'threshold' => '400.0000',
        ]);
        $alert->id = 1;
        $alert->setRelation('currency', $currency);
        $alert->setRelation('user', $user);

        $rate = new CurrencyRate([
            'buy_rate' => '402.5000',
            'sell_rate' => '406.0000',
        ]);
        $rate->id = 1;
        $rate->setRelation('organization', $organization);

        return new RateAlertTriggered($alert, $rate);
    }

    private function quoteRequestLinkResent(): QuoteRequestLinkResent
    {
        $first = new QuoteRequest([
            'destination_country' => 'GR',
            'locale' => 'en',
            'guest_name' => 'Davit Sargsyan',
            'check_in' => now()->addWeeks(2),
            'check_out' => now()->addWeeks(3),
            'adults' => 2,
            'children' => 1,
            'expires_at' => now()->addDays(7),
        ]);
        $first->id = 1;
        $first->setRelation('user', null);

        $second = new QuoteRequest([
            'destination_country' => 'CY',
            'locale' => 'en',
            'guest_name' => 'Davit Sargsyan',
            'check_in' => now()->addWeeks(5),
            'check_out' => now()->addWeeks(6),
            'adults' => 2,
            'children' => 0,
            'expires_at' => now()->addDays(7),
        ]);
        $second->id = 2;
        $second->setRelation('user', null);

        return new QuoteRequestLinkResent(new Collection([$first, $second]));
    }

    private function quoteResponseReceived(): QuoteResponseReceived
    {
        $organization = $this->organization('Sunny Travel', 'sunny-travel');
        $organization->id = 1;

        $quoteRequest = new QuoteRequest(['destination_country' => 'GR', 'locale' => 'en', 'guest_name' => 'Davit Sargsyan']);
        $quoteRequest->id = 1;
        $quoteRequest->setRelation('user', null);

        $quoteResponse = new QuoteResponse([
            'status' => QuoteResponse::STATUS_RESPONDED,
        ]);
        $quoteResponse->id = 1;
        $quoteResponse->setRelation('organization', $organization);
        $quoteResponse->setRelation('quoteRequest', $quoteRequest);

        return new QuoteResponseReceived($quoteResponse, url('/en/tourism/requests/1'));
    }
}
