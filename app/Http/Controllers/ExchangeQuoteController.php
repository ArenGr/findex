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
use App\Support\SafeRedirectUrl;
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

        $currencies = Currency::where('is_active', true)
            ->whereIn('code', array_keys($minimums))
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Currency $currency) => Organization::exchangePartnersForCurrency($currency->id)->exists())
            ->values();

        $selectedCurrency = $currencies->firstWhere('code', $request->query('currency')) ?? $currencies->first();

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

    // The windows a visitor can choose, in minutes.
    public const VALID_FOR = [
        '15m' => 15,
        '30m' => 30,
        '1h' => 60,
    ];

    public function store(Request $request): RedirectResponse
    {
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
            // One field, and only for guests.
            'guest_email' => [Rule::requiredIf(! $request->user()), 'nullable', ValidationRules::email(), 'max:255'],
        ], attributes: [
            'guest_email' => __('exchange_quotes.modal.email_label'),
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
            'guest_email' => $request->user() ? null : $validated['guest_email'],
            'locale' => app()->getLocale(),
            'currency_id' => $currency->id,
            'amount' => $validated['amount'],
            'rate_field' => $validated['rate_field'],
            'preferred_city' => $preferredCity,
            'notes' => $validated['notes'] ?? null,
            'expires_at' => now()->addMinutes(self::VALID_FOR[$validFor]),
        ]);

        SendExchangeQuoteToPartnersJob::dispatch($exchangeQuoteRequest);

        $resultsUrl = $exchangeQuoteRequest->signedResultsUrl();

        if ($exchangeQuoteRequest->requester_email) {
            Mail::to($exchangeQuoteRequest->requester_email)
                ->locale($exchangeQuoteRequest->locale)
                ->send(new ExchangeQuoteRequestSubmitted($exchangeQuoteRequest, $resultsUrl, $partners->count()));
        }

        $request->session()->put('exchange.active_request', [
            'id' => $exchangeQuoteRequest->id,
            'url' => $resultsUrl,
        ]);

        return ($request->user()
            ? redirect()->route('exchange.show', $exchangeQuoteRequest)
            : redirect($resultsUrl))->with([
                'status' => 'exchange-quote-submitted',
                'contacted_count' => $partners->count(),
            ]);
    }

    public function cancel(Request $request, string $locale, string $exchangeQuoteRequest): RedirectResponse
    {
        $exchangeQuoteRequest = ExchangeQuoteRequest::findOrFail($exchangeQuoteRequest);

        $isOwner = $request->user() && $request->user()->id === $exchangeQuoteRequest->user_id;

        abort_unless($isOwner || $request->hasValidSignature(), 403);

        // An accepted offer is a deal, not a pending question - there is nothing left to cancel.
        abort_if($exchangeQuoteRequest->responses()->where('status', ExchangeQuoteResponse::STATUS_ACCEPTED)->exists(), 403);

        if ($exchangeQuoteRequest->is_open) {
            $exchangeQuoteRequest->update(['expires_at' => now()]);
        }

        return redirect()->to($exchangeQuoteRequest->signedResultsUrl())
            ->with('status', 'exchange-quote-cancelled');
    }

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

    // Picking an offer.
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

        abort_unless($exchangeQuoteRequest->is_open, 410);

        $chosen = $exchangeQuoteRequest->responses->firstWhere('id', (int) $response);

        abort_if($chosen === null || ! $chosen->has_replied, 404);

        $exchangeQuoteRequest->responses()
            ->where('status', ExchangeQuoteResponse::STATUS_ACCEPTED)
            ->update(['status' => ExchangeQuoteResponse::STATUS_RESPONDED, 'accepted_at' => null]);

        $chosen->forceFill([
            'status' => ExchangeQuoteResponse::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ])->save();

        $notifier->notifyAccepted($chosen);

        return redirect()
            ->to(SafeRedirectUrl::resolve($request, $request->headers->get('referer'), route('exchange.show', [$exchangeQuoteRequest])))
            ->with('status', 'offer-accepted');
    }

    /**
     * What each offer is actually worth, in money.
     *
     * @return array{publicBest: float|null, offerValues: array<int, array>, bestExtra: float|null}
     */
    private function offerValue(ExchangeQuoteRequest $exchangeQuoteRequest): array
    {
        $field = $exchangeQuoteRequest->rate_field;
        $amount = (float) $exchangeQuoteRequest->amount;

        // Selling the currency, the highest buy rate wins; buying it, the lowest sell rate does.
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
            // The headline.
            'bestExtra' => collect($offerValues)->pluck('extra')->filter(fn ($extra) => $extra !== null)->max() ?: null,
            'wantsHigh' => $wantsHigh,
        ];
    }
}
