<?php

namespace App\Http\Controllers;

use App\Jobs\SendExchangeQuoteToPartnersJob;
use App\Mail\ExchangeQuoteLinkResent;
use App\Mail\ExchangeQuoteRequestSubmitted;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeQuoteRequest;
use App\Models\Organization;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExchangeQuoteController extends Controller
{
    /**
     * A currency code has no country of its own to derive a flag from the
     * way QuoteRequestController::worldCountries() does for ISO-3166
     * countries (that trick needs a 2-letter country code, not a 3-letter
     * currency one) - so this is a small hand-picked map to one
     * representative country/region per currency, the same convention
     * used by most currency-converter apps. EUR gets the real EU flag
     * rather than any single member state's.
     */
    private const CURRENCY_FLAGS = [
        'AMD' => '🇦🇲',
        'USD' => '🇺🇸',
        'EUR' => '🇪🇺',
        'GBP' => '🇬🇧',
        'CHF' => '🇨🇭',
        'RUR' => '🇷🇺',
        'GEL' => '🇬🇪',
        'AED' => '🇦🇪',
        'CNY' => '🇨🇳',
        'KZT' => '🇰🇿',
        'CAD' => '🇨🇦',
        'AUD' => '🇦🇺',
    ];

    public function create(Request $request): View
    {
        $minimums = config('exchange-quotes.minimum_amounts');

        // Only currencies both configured with a minimum and currently
        // served by at least one matching exchange office - showing a
        // currency nobody can quote would just be a guaranteed dead end at
        // submit time (see store()'s pre-check).
        $currencies = Currency::where('is_active', true)
            ->whereIn('code', array_keys($minimums))
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Currency $currency) => Organization::exchangePartnersForCurrency($currency->id)->exists())
            ->values();

        // Pre-fills from the /rates page's "Get a better rate" link
        // (?currency=USD) - falls back to the first available currency when
        // absent or not actually offered.
        $selectedCurrency = $currencies->firstWhere('code', $request->query('currency')) ?? $currencies->first();

        return view('exchange.request', [
            'currencies' => $currencies,
            'minimums' => $minimums,
            'selectedCurrency' => $selectedCurrency,
            'currencyFlags' => self::CURRENCY_FLAGS,
            'cities' => $this->exchangeCities(),
        ]);
    }

    /**
     * Cities where at least one exchange office has an active branch - the
     * optional "preferred region" a visitor can restrict their request to.
     * Scoped to exchange-type organizations specifically, since those are
     * the only ones this form ever contacts (see
     * Organization::exchangePartnersForCurrency).
     */
    private function exchangeCities(): Collection
    {
        return Branch::active()
            ->whereHas('organization', fn ($query) => $query->where('type', 'exchange'))
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');
    }

    public function resendForm(): View
    {
        return view('exchange.resend');
    }

    /**
     * A guest has no account to log back into, so a lost confirmation email
     * means a lost results link with no way back - this re-sends it. The
     * response is identical whether or not a match is found, so this can't
     * be used to check which email addresses have filed a request. Same
     * shape as QuoteRequestController::resend (travel).
     */
    public function resend(Request $request): RedirectResponse
    {
        if ($request->filled('company')) {
            return redirect()->route('exchange.resend')->with('status', 'resend-requested');
        }

        $validated = $request->validate([
            'email' => ['required', ValidationRules::email(), 'max:255'],
        ], attributes: [
            'email' => __('exchange_quotes.resend.email_placeholder'),
        ]);

        $openRequests = ExchangeQuoteRequest::query()
            ->whereNull('user_id')
            ->where('guest_email', $validated['email'])
            ->with('currency')
            ->open()
            ->latest()
            ->get();

        if ($openRequests->isNotEmpty()) {
            Mail::to($validated['email'])
                ->locale($openRequests->first()->locale)
                ->send(new ExchangeQuoteLinkResent($openRequests));
        }

        return redirect()->route('exchange.resend')->with('status', 'resend-requested');
    }

    /**
     * The only place a signed-in user can see every exchange rate request
     * they've ever filed - same reasoning as QuoteRequestController::mine.
     */
    public function mine(Request $request): View
    {
        $exchangeQuoteRequests = $request->user()->exchangeQuoteRequests()
            ->with('currency')
            ->withCount([
                'responses',
                'responses as replied_responses_count' => fn ($query) => $query->whereNotNull('responded_at'),
            ])
            ->latest()
            ->get();

        return view('exchange.mine', ['exchangeQuoteRequests' => $exchangeQuoteRequests]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: see QuoteRequestController::store for why this is
        // silently ignored rather than rejected outright.
        if ($request->filled('company')) {
            return redirect()->route('exchange.request');
        }

        if ($request->user() && ! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('exchange.request')->with('status', 'email-verification-required');
        }

        $minimums = config('exchange-quotes.minimum_amounts');
        $cities = $this->exchangeCities();

        $validated = $request->validate([
            'currency_code' => ['required', 'string', Rule::in(array_keys($minimums))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'rate_field' => ['required', 'string', Rule::in(['buy_rate', 'sell_rate'])],
            'preferred_city' => ['nullable', 'string', Rule::in($cities)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'guest_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'min:2', 'max:60'],
            'guest_email' => [Rule::requiredIf(! $request->user()), 'nullable', ValidationRules::email(), 'max:255'],
            'consent' => ['accepted'],
        ], attributes: [
            'guest_name' => __('tourism.request.your_name'),
            'guest_email' => __('tourism.request.your_email'),
        ]);

        $currency = Currency::where('code', $validated['currency_code'])->where('is_active', true)->firstOrFail();

        if ((float) $validated['amount'] < $minimums[$currency->code]) {
            return back()->withInput()->withErrors([
                'amount' => __('exchange_quotes.request.below_minimum', [
                    'amount' => number_format($minimums[$currency->code]),
                    'currency' => $currency->code,
                ]),
            ]);
        }

        $preferredCity = $validated['preferred_city'] ?? null;

        $partners = Organization::exchangePartnersForCurrency($currency->id, $preferredCity)->get();

        if ($partners->isEmpty()) {
            return back()->withInput()->withErrors($preferredCity ? [
                'preferred_city' => __('exchange_quotes.request.no_partners_for_region'),
            ] : [
                'currency_code' => __('exchange_quotes.request.no_partners_for_currency'),
            ]);
        }

        $exchangeQuoteRequest = ExchangeQuoteRequest::create([
            'user_id' => $request->user()?->id,
            'guest_name' => $request->user() ? null : $validated['guest_name'],
            'guest_email' => $request->user() ? null : $validated['guest_email'],
            'locale' => app()->getLocale(),
            'currency_id' => $currency->id,
            'amount' => $validated['amount'],
            'rate_field' => $validated['rate_field'],
            'preferred_city' => $preferredCity,
            'notes' => $validated['notes'] ?? null,
            // Shorter than travel's 14 days - exchange rates move day to
            // day, a 2-week-old "offer" would be meaningless.
            'expires_at' => now()->addDays(7),
        ]);

        SendExchangeQuoteToPartnersJob::dispatch($exchangeQuoteRequest);

        $resultsUrl = $exchangeQuoteRequest->signedResultsUrl();

        if ($exchangeQuoteRequest->requester_email) {
            Mail::to($exchangeQuoteRequest->requester_email)
                ->locale($exchangeQuoteRequest->locale)
                ->send(new ExchangeQuoteRequestSubmitted($exchangeQuoteRequest, $resultsUrl, $partners->count()));
        }

        return ($request->user()
            ? redirect()->route('exchange.show', $exchangeQuoteRequest)
            : redirect($resultsUrl))->with([
                'status' => 'exchange-quote-submitted',
                'contacted_count' => $partners->count(),
            ]);
    }

    /**
     * Resolved manually rather than via implicit route-model binding - see
     * QuoteRequestController::show for why (access is gated on either
     * being the owning user or holding a valid signed link, which implicit
     * binding can't express).
     */
    public function show(Request $request, string $locale, string $exchangeQuoteRequest): View
    {
        $exchangeQuoteRequest = ExchangeQuoteRequest::with(['responses.organization', 'currency'])->findOrFail($exchangeQuoteRequest);

        $isOwner = $request->user() && $request->user()->id === $exchangeQuoteRequest->user_id;

        abort_unless($isOwner || $request->hasValidSignature(), 403);

        return view('exchange.show', ['exchangeQuoteRequest' => $exchangeQuoteRequest]);
    }
}
