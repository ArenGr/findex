<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Jobs\SendExchangeQuoteToPartnersJob;
use App\Mail\ExchangeQuoteLinkResent;
use App\Mail\ExchangeQuoteRequestSubmitted;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\ExchangeQuoteRequest;
use App\Models\ExchangeQuoteResponse;
use App\Models\Organization;
use App\Services\Notifications\ExchangeNotifierInterface;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExchangeQuoteController extends Controller
{
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

        // Which way round the exchange goes, handed over by /rates so the
        // visitor is not asked a question they have already answered. Validated
        // rather than trusted: it lands straight in a form field, and only
        // these two values mean anything to store().
        $prefilledDirection = in_array($request->query('rate_field'), ['buy_rate', 'sell_rate'], true)
            ? $request->query('rate_field')
            : 'buy_rate';

        return view('exchange.request', [
            'prefilledDirection' => $prefilledDirection,
            'currencies' => $currencies,
            'minimums' => $minimums,
            'selectedCurrency' => $selectedCurrency,
            'currencyFlags' => Currency::FLAGS,
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

    /**
     * The windows a visitor can choose, in minutes. Short by design: the whole
     * point is an office holding a rate, and nobody holds one overnight.
     */
    public const VALID_FOR = [
        '15m' => 15,
        '30m' => 30,
        '1h' => 60,
        'today' => 480,
    ];

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
            'valid_for' => ['nullable', Rule::in(array_keys(self::VALID_FOR))],
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
        $validFor = $validated['valid_for'] ?? '1h';

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
            // How long the visitor is prepared to wait, rather than a flat
            // week. A rate held for seven days is not a rate anyone is really
            // holding - and an office answering a two-day-old request is
            // quoting into a market that has moved.
            'expires_at' => now()->addMinutes(self::VALID_FOR[$validFor]),
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

        return view('exchange.show', [
            'exchangeQuoteRequest' => $exchangeQuoteRequest,
            ...$this->offerValue($exchangeQuoteRequest),
        ]);
    }

    /**
     * Picking an offer.
     *
     * Nothing about the visitor is sent anywhere: the office is told nothing at
     * this point at all. What the visitor gets is a code - FX-48372-A - which
     * the office can look up against the request it already answered. That is
     * the whole handshake, and it carries no name, no email and no phone.
     */
    public function accept(
        Request $request,
        string $locale,
        string $exchangeQuoteRequest,
        string $response,
        ExchangeNotifierInterface $notifier,
    ): RedirectResponse {
        $exchangeQuoteRequest = ExchangeQuoteRequest::with('responses')->findOrFail($exchangeQuoteRequest);

        $isOwner = $request->user() && $request->user()->id === $exchangeQuoteRequest->user_id;

        abort_unless($isOwner || $request->hasValidSignature(), 403);

        // A closed request cannot be acted on: the offers behind it have
        // expired and the office is no longer holding that rate.
        abort_unless($exchangeQuoteRequest->is_open, 410);

        $chosen = $exchangeQuoteRequest->responses->firstWhere('id', (int) $response);

        abort_if($chosen === null || ! $chosen->has_replied, 404);

        // Changing your mind is allowed while the request is open, so the
        // others drop back to a plain reply rather than being frozen out.
        $exchangeQuoteRequest->responses()
            ->where('status', ExchangeQuoteResponse::STATUS_ACCEPTED)
            ->update(['status' => ExchangeQuoteResponse::STATUS_RESPONDED, 'accepted_at' => null]);

        $chosen->forceFill([
            'status' => ExchangeQuoteResponse::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ])->save();

        // The office has to know somebody is coming - both so they hold the
        // rate, and so they can tell us afterwards whether the customer turned
        // up. That report is the only way Findex ever learns whether a request
        // became a real transaction; there is no affiliate link to follow and
        // no payment passing through us.
        $notifier->notifyAccepted($chosen);

        return redirect()
            ->to($request->headers->get('referer') ?: route('exchange.show', [$exchangeQuoteRequest]))
            ->with('status', 'offer-accepted');
    }

    /**
     * What each offer is actually worth, in money.
     *
     * The page has always shown rates - 386.20 against a posted 385.00 - and
     * left the visitor to work out that the difference is 6,000 dram on their
     * amount. That subtraction is the entire point of the feature, so the page
     * does it.
     *
     * Measured against the best rate publicly available right now rather than
     * against the rate that office happened to be posting when the request went
     * out: the honest question is "did asking beat just walking into the best
     * place on the list", not "did this office improve on itself".
     *
     * @return array{publicBest: float|null, offerValues: array<int, array>, bestExtra: float|null}
     */
    private function offerValue(ExchangeQuoteRequest $exchangeQuoteRequest): array
    {
        $field = $exchangeQuoteRequest->rate_field;
        $amount = (float) $exchangeQuoteRequest->amount;

        // Selling the currency, the highest buy rate wins; buying it, the
        // lowest sell rate does.
        $wantsHigh = $field === 'buy_rate';

        $publicRates = CurrencyRate::query()
            ->where('currency_id', $exchangeQuoteRequest->currency_id)
            ->where('rate_type', RateType::CASH)
            ->whereHas('organization', fn ($query) => $query->active())
            ->pluck($field)
            ->map(fn ($rate) => (float) $rate)
            ->filter();

        $publicBest = $publicRates->isEmpty()
            ? null
            : ($wantsHigh ? $publicRates->max() : $publicRates->min());

        $offerValues = [];

        foreach ($exchangeQuoteRequest->responses as $response) {
            if (! $response->has_replied || $response->offered_rate === null) {
                continue;
            }

            $offered = (float) $response->offered_rate;

            $offerValues[$response->id] = [
                // Dram either way: what you walk out with when selling the
                // currency, what you hand over when buying it.
                'total' => $amount * $offered,
                'extra' => $publicBest === null
                    ? null
                    : $amount * ($wantsHigh ? $offered - $publicBest : $publicBest - $offered),
            ];
        }

        return [
            'publicBest' => $publicBest,
            'publicBestTotal' => $publicBest === null ? null : $amount * $publicBest,
            'offerValues' => $offerValues,
            // The headline. Null when nobody beat the open market, because
            // "Findex got you 0 more" is not a claim worth making.
            'bestExtra' => collect($offerValues)->pluck('extra')->filter(fn ($extra) => $extra !== null)->max() ?: null,
            'wantsHigh' => $wantsHigh,
        ];
    }
}
