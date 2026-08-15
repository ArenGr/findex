<?php

namespace Database\Seeders;

use App\Enums\RateType;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\ExchangeQuoteRequest;
use App\Models\ExchangeQuoteResponse;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * A worked exchange request with offers already on it, so the results page can
 * be looked at without waiting on real exchange offices to answer a Telegram
 * message.
 *
 * Local-testing data only - deliberately not called from DatabaseSeeder::run(),
 * so it can never land in a production seed. It also refuses to run in
 * production outright, because a fabricated request with fabricated offers is
 * exactly the sort of thing that should never appear in a real dataset.
 *
 * Run with: php artisan db:seed --class=ExchangeOfferDemoSeeder
 */
class ExchangeOfferDemoSeeder extends Seeder
{
    private const GUEST_EMAIL = 'demo-offers@findex.test';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('ExchangeOfferDemoSeeder is for local use only.');

            return;
        }

        $currency = Currency::where('code', 'USD')->where('is_active', true)->first();

        if ($currency === null) {
            $this->command?->error('No active USD currency - run the main seeders first.');

            return;
        }

        $offices = Organization::active()->where('type', 'exchange')->take(5)->get();

        if ($offices->count() < 3) {
            $this->command?->error('Need at least three exchange offices - run ExchangeOrgSeeder first.');

            return;
        }

        // Re-runnable: clear the previous demo rather than piling up a new
        // request every time somebody wants another look at the page.
        ExchangeQuoteRequest::where('guest_email', self::GUEST_EMAIL)->each(function (ExchangeQuoteRequest $old) {
            $old->responses()->delete();
            $old->delete();
        });

        $request = ExchangeQuoteRequest::create([
            'currency_id' => $currency->id,
            'amount' => 5000,
            // The visitor is handing over dollars, so the office is buying.
            'rate_field' => 'buy_rate',
            'guest_name' => 'Demo visitor',
            'guest_email' => self::GUEST_EMAIL,
            'locale' => 'en',
            'preferred_city' => 'Yerevan',
            // One of the real windows, so the demo shows what a request
            // actually looks like rather than a day-long one nobody can make.
            'expires_at' => now()->addHour(),
        ]);

        // What the market is publicly offering, so the "extra value" figures on
        // the page are measured against something real rather than invented.
        $publicBest = (float) CurrencyRate::query()
            ->where('currency_id', $currency->id)
            ->where('rate_type', RateType::CASH)
            ->whereHas('organization', fn ($query) => $query->active())
            ->max('buy_rate');

        // A spread of outcomes, because the page has to look right in all of
        // them: three real offers, one office still thinking, one that passed.
        $offers = [
            ['letter' => 'A', 'rate' => $publicBest + 1.70, 'minutes' => 11, 'note' => 'Happy to hold this rate for an hour.'],
            ['letter' => 'B', 'rate' => $publicBest + 1.50, 'minutes' => 24, 'note' => null],
            ['letter' => 'C', 'rate' => $publicBest + 1.20, 'minutes' => 38, 'note' => 'Bring the code, cash is ready.'],
            ['letter' => 'D', 'rate' => null, 'minutes' => null, 'note' => null],
            ['letter' => 'E', 'rate' => null, 'minutes' => 52, 'note' => null, 'declined' => true],
        ];

        foreach ($offers as $index => $offer) {
            if (! isset($offices[$index])) {
                continue;
            }

            $request->responses()->create([
                'organization_id' => $offices[$index]->id,
                'response_token' => Str::random(40),
                'offer_letter' => $offer['letter'],
                'posted_rate' => $publicBest,
                'offered_rate' => $offer['rate'],
                'reply_text' => $offer['note'],
                'status' => match (true) {
                    ($offer['declined'] ?? false) => ExchangeQuoteResponse::STATUS_DECLINED,
                    $offer['rate'] !== null => ExchangeQuoteResponse::STATUS_RESPONDED,
                    default => ExchangeQuoteResponse::STATUS_PENDING,
                },
                'responded_at' => $offer['minutes'] ? now()->subMinutes($offer['minutes']) : null,
            ]);
        }

        $this->command?->info("Demo request {$request->public_code} created with {$request->responses()->count()} offices.");
        $this->command?->line('Open the results page at:');
        $this->command?->line(URL::signedRoute('exchange.show', [
            'locale' => 'en',
            'exchangeQuoteRequest' => $request->id,
        ]));
        $this->command?->line('');
        $this->command?->comment('The link is signed against APP_URL ('.config('app.url').').');
        $this->command?->comment('Browsing on another host? Re-run with APP_URL set to it, e.g.');
        $this->command?->comment('  APP_URL=http://127.0.0.1:8123 php artisan db:seed --class=ExchangeOfferDemoSeeder');
    }
}
