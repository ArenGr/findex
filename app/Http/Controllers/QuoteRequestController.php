<?php

namespace App\Http\Controllers;

use App\Enums\QuoteRequestStatus;
use App\Jobs\SendQuoteRequestToPartnersJob;
use App\Mail\QuoteRequestLinkResent;
use App\Mail\QuoteRequestSubmitted;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteSuggestion;
use App\Models\User;
use App\Services\CurrencyConverter;
use App\Services\Notifications\PartnerNotifierInterface;
use App\Services\TourismPriceData;
use App\Services\TravelOfferComparison;
use App\Support\SafeRedirectUrl;
use App\Support\TravelPartners;
use App\Support\TravelPresets;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Intl\Countries;

class QuoteRequestController extends Controller
{
    private const TYPICAL_PRICE_MIN_SUGGESTIONS = 3;

    private const TYPICAL_PRICE_MIN_ORGS = 2;

    private const BUDGET_CURRENCIES = ['USD', 'EUR', 'RUR'];

    public const MAX_COMPARED_OFFERS = 4;

    public function create(TourismPriceData $priceData): View
    {
        $typicalPrices = $this->typicalPrices($priceData);

        return view('tourism.request', [
            'destinations' => QuoteRequest::DESTINATIONS,
            'countries' => $this->worldCountries(),
            'typicalPrices' => $typicalPrices,
            // The popular trips offered above the form. They read the same
            // typical prices the destination picker does, so a preset never
            // quotes a figure the rest of the page would not.
            'presets' => TravelPresets::all($typicalPrices),
            'flightOptions' => self::labelled(QuoteRequest::FLIGHT_PREFERENCES, 'tourism.flights.'),
            'hotelOptions' => self::labelled(QuoteRequest::HOTEL_PREFERENCES, 'tourism.hotel_class.'),
            'mealOptions' => self::labelled(QuoteRequest::MEAL_PREFERENCES, 'tourism.meals.'),
            'priorityOptions' => self::labelled(QuoteRequest::PRIORITIES, 'tourism.priorities.'),
            'budgetBands' => QuoteRequest::BUDGET_BANDS,
            'budgetBandLabels' => self::labelled(array_keys(QuoteRequest::BUDGET_BANDS), 'tourism.budget_bands.'),
            'dateFlexibilityOptions' => self::labelled(QuoteRequest::DATE_FLEXIBILITY_OPTIONS, 'tourism.date_flexibility.'),
            'maxPriorities' => QuoteRequest::MAX_PRIORITIES,
            'maxDestinations' => QuoteRequest::MAX_DESTINATIONS,
            'maxChildren' => QuoteRequest::MAX_CHILDREN,
            'maxChildAge' => QuoteRequest::MAX_CHILD_AGE,
            // Presentation only - the "trusted by" strip. Cached, so it
            // does not add a query to this page's asserted budget.
            'partners' => TravelPartners::all(),
        ]);
    }

    private static function labelled(array $values, string $keyPrefix): array
    {
        return collect($values)->mapWithKeys(fn ($value) => [$value => __($keyPrefix.$value)])->all();
    }

    private function worldCountries(): array
    {
        return collect(Countries::getNames(app()->getLocale()))
            ->map(fn ($name, $code) => [
                'code' => $code,
                'name' => $name,
                'flag' => mb_chr(127462 + (ord($code[0]) - 65)).mb_chr(127462 + (ord($code[1]) - 65)),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    private function typicalPrices(TourismPriceData $priceData): array
    {
        $rows = $priceData->respondedSuggestionAmounts(QuoteRequest::DESTINATIONS);

        return collect(QuoteRequest::DESTINATIONS)
            ->mapWithKeys(function ($countryCode) use ($rows) {
                $forDestination = $rows->where('destination_country', $countryCode);
                $orgCount = $forDestination->pluck('organization_id')->unique()->count();

                $hasEnoughData = $forDestination->count() >= self::TYPICAL_PRICE_MIN_SUGGESTIONS
                    && $orgCount >= self::TYPICAL_PRICE_MIN_ORGS;

                return [$countryCode => $hasEnoughData ? round($forDestination->avg('amount_amd')) : null];
            })
            ->all();
    }

    public function mine(Request $request): View
    {
        $tab = $request->query('tab') === 'past' ? 'past' : 'active';

        $quoteRequests = $request->user()->quoteRequests()
            ->withProgressCounts()
            ->when(
                $tab === 'active',
                fn ($query) => $query->open(),
                fn ($query) => $query->where(fn ($query) => $query
                    ->where('expires_at', '<=', now())
                    ->orWhere('status', QuoteRequestStatus::CLOSED->value)),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('tourism.mine', [
            'quoteRequests' => $quoteRequests,
            'tab' => $tab,
            'activeCount' => $request->user()->quoteRequests()->open()->count(),
        ]);
    }

    public function resendForm(): View
    {
        return view('tourism.resend');
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->filled('company')) {
            return redirect()->route('tourism.resend')->with('status', 'resend-requested');
        }

        $validated = $request->validate([
            'email' => ['required', ValidationRules::email(), 'max:255'],
        ], attributes: [
            'email' => __('tourism.request.your_email'),
        ]);

        $openRequests = QuoteRequest::query()
            ->whereNull('user_id')
            ->where('guest_email', $validated['email'])
            ->open()
            ->latest()
            ->get();

        if ($openRequests->isNotEmpty()) {
            Mail::to($validated['email'])
                ->locale($openRequests->first()->locale)
                ->send(new QuoteRequestLinkResent($openRequests));
        }

        return redirect()->route('tourism.resend')->with('status', 'resend-requested');
    }

    private function rules(Request $request): array
    {
        return [
            'departure_location' => ['required', 'string', 'max:120'],
            'destination_countries' => ['nullable', 'array', 'max:'.QuoteRequest::MAX_DESTINATIONS],
            'destination_countries.*' => ['distinct', Rule::in(array_keys(Countries::getNames()))],
            'open_to_suggestions' => ['nullable', 'boolean'],
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'date_flexibility' => ['nullable', Rule::in(QuoteRequest::DATE_FLEXIBILITY_OPTIONS)],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:'.QuoteRequest::MAX_CHILDREN],
            'child_ages' => ['nullable', 'array', 'max:'.QuoteRequest::MAX_CHILDREN],
            'child_ages.*' => ['required', 'integer', 'min:0', 'max:'.QuoteRequest::MAX_CHILD_AGE],
            'flight_preference' => ['nullable', Rule::in(QuoteRequest::FLIGHT_PREFERENCES)],
            'hotel_preference' => ['nullable', Rule::in(QuoteRequest::HOTEL_PREFERENCES)],
            'meal_preference' => ['nullable', Rule::in(QuoteRequest::MEAL_PREFERENCES)],
            'priorities' => ['nullable', 'array', 'max:'.QuoteRequest::MAX_PRIORITIES],
            'priorities.*' => ['distinct', Rule::in(QuoteRequest::PRIORITIES)],
            'insurance' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'budget_band' => ['nullable', Rule::in(array_keys(QuoteRequest::BUDGET_BANDS))],
            'budget_min_amd' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'budget_max_amd' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'budget_currency' => ['nullable', Rule::in(self::budgetCurrencyCodes())],
            'guest_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'min:2', 'max:60'],
            'guest_email' => [Rule::requiredIf(! $request->user()), 'nullable', ValidationRules::email(), 'max:255'],
            'consent' => ['accepted'],
        ];
    }

    private static function budgetCurrencyCodes(): array
    {
        return array_merge(['AMD'], self::BUDGET_CURRENCIES);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('company')) {
            return redirect()->route('tourism.request');
        }

        if ($request->user() && ! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('tourism.request')->with('status', 'email-verification-required');
        }

        $validated = $request->validate($this->rules($request), attributes: [
            'guest_name' => __('tourism.request.your_name'),
            'guest_email' => __('tourism.request.your_email'),
        ]);

        $destinations = array_values($validated['destination_countries'] ?? []);
        $openToSuggestions = $request->boolean('open_to_suggestions');

        if ($destinations === [] && ! $openToSuggestions) {
            return back()->withInput()->withErrors([
                'destination_countries' => __('tourism.request.destination_required'),
            ]);
        }

        $childAges = array_map('intval', array_values($validated['child_ages'] ?? []));
        $children = (int) ($validated['children'] ?? 0);

        if (count($childAges) !== $children) {
            return back()->withInput()->withErrors([
                'child_ages' => __('tourism.request.child_ages_required', ['count' => $children]),
            ]);
        }

        $validated = $this->applyBudgetBand($validated);
        if (isset($validated['budget_min_amd'], $validated['budget_max_amd']) && $validated['budget_max_amd'] < $validated['budget_min_amd']) {
            return back()->withInput()->withErrors([
                'budget_max_amd' => __('tourism.request.budget_max_below_min'),
            ]);
        }

        $quoteRequest = new QuoteRequest([
            'user_id' => $request->user()?->id,
            'guest_name' => $request->user() ? null : $validated['guest_name'],
            'guest_email' => $request->user() ? null : $validated['guest_email'],
            'locale' => app()->getLocale(),
            'departure_location' => $validated['departure_location'] ?? null,
            'open_to_suggestions' => $openToSuggestions,
            'hotel_name' => $validated['hotel_name'] ?? null,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'date_flexibility' => $validated['date_flexibility'] ?? null,
            'adults' => $validated['adults'],
            'children' => $children,
            'child_ages' => $childAges,
            'flight_preference' => $validated['flight_preference'] ?? QuoteRequest::FLIGHT_FLEXIBLE,
            'hotel_preference' => $validated['hotel_preference'] ?? QuoteRequest::HOTEL_ANY,
            'meal_preference' => $validated['meal_preference'] ?? QuoteRequest::MEAL_ANY,
            // array_values so a partially-unchecked set of boxes is stored
            // as a JSON list, not an object with gappy numeric keys.
            'priorities' => array_values($validated['priorities'] ?? []),
            'insurance' => $request->boolean('insurance'),
            'notes' => $validated['notes'] ?? null,
            'budget_min_amd' => $validated['budget_min_amd'] ?? null,
            'budget_max_amd' => $validated['budget_max_amd'] ?? null,
            'budget_currency' => $validated['budget_currency'] ?? 'AMD',
            'status' => QuoteRequestStatus::SUBMITTED,
            'expires_at' => now()->addDays(14),
        ]);

        $quoteRequest->setDestinations($destinations);
        $partners = Organization::tourismPartnersForRequest($quoteRequest)->get();
        if ($partners->isEmpty()) {
            return back()->withInput()->withErrors([
                'destination_countries' => __('tourism.request.no_partners_for_destination'),
            ]);
        }

        if ($existing = $this->existingOpenRequest($request, $validated, $destinations[0] ?? null)) {
            return redirect()->route('tourism.show', $existing)->with('status', 'quote-request-duplicate');
        }

        $quoteRequest->save();

        SendQuoteRequestToPartnersJob::dispatch($quoteRequest);

        $resultsUrl = $quoteRequest->signedResultsUrl();

        if ($quoteRequest->requester_email) {
            Mail::to($quoteRequest->requester_email)
                ->locale($quoteRequest->locale)
                ->send(new QuoteRequestSubmitted($quoteRequest, $resultsUrl, $partners->count()));
        }

        return ($request->user()
            ? redirect()->route('tourism.show', $quoteRequest)
            : redirect($resultsUrl))->with([
                'status' => 'quote-request-submitted',
                'contacted_count' => $partners->count(),
            ]);
    }

    private function existingOpenRequest(Request $request, array $validated, ?string $destinationCountry): ?QuoteRequest
    {
        return QuoteRequest::query()
            ->when(
                $request->user(),
                fn ($query) => $query->where('user_id', $request->user()->id),
                fn ($query) => $query->whereNull('user_id')->where('guest_email', $validated['guest_email']),
            )
            ->where('destination_country', $destinationCountry)
            ->whereDate('check_in', $validated['check_in'])
            ->whereDate('check_out', $validated['check_out'])
            ->open()
            ->first();
    }

    private function applyBudgetBand(array $validated): array
    {
        if (! isset($validated['budget_band'])) {
            return $validated;
        }

        $band = QuoteRequest::BUDGET_BANDS[$validated['budget_band']];

        $validated['budget_min_amd'] = $band['min'];
        $validated['budget_max_amd'] = $band['max'];

        return $validated;
    }

    public function show(Request $request, string $locale, string $quoteRequest): View
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest, withProgress: true);

        return view('tourism.show', [
            'quoteRequest' => $quoteRequest,
            'offersUrl' => $quoteRequest->signedOffersUrl(),
            'compareUrl' => $quoteRequest->signedUrlFor('tourism.compare'),
        ]);
    }

    public function offers(Request $request, string $locale, string $quoteRequest, CurrencyConverter $currencyConverter): View
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest);

        return view('tourism.offers', [
            'quoteRequest' => $quoteRequest,
            'preferredCurrency' => $currencyConverter->preferredCurrencyForLocale(app()->getLocale()),
            'currencyConverter' => $currencyConverter,
            'compareUrl' => $quoteRequest->signedUrlFor('tourism.compare'),
            'statusUrl' => $quoteRequest->signedResultsUrl(),
        ]);
    }

    public function compare(Request $request, string $locale, string $quoteRequest, TravelOfferComparison $comparison): View
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest);

        $offers = $comparison->for($quoteRequest);

        $requested = collect(explode(',', (string) $request->query('offers')))
            ->map(fn ($id) => (int) trim($id))
            ->filter()
            ->all();

        $selected = $requested === []
            ? $offers->take(self::MAX_COMPARED_OFFERS)
            : $offers->whereIn('offer.id', $requested)->take(self::MAX_COMPARED_OFFERS);

        return view('tourism.compare', [
            'quoteRequest' => $quoteRequest,
            'offers' => $offers,
            'selected' => $selected->values(),
            'offersUrl' => $quoteRequest->signedOffersUrl(),
        ]);
    }

    public function offer(Request $request, string $locale, string $quoteRequest, string $suggestion, TravelOfferComparison $comparison): View
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest);
        $row = $comparison->for($quoteRequest)->firstWhere('offer.id', (int) $suggestion);
        abort_if($row === null, 404);

        return view('tourism.offer', [
            'quoteRequest' => $quoteRequest,
            'offer' => $row['offer'],
            'response' => $row['response'],
            'organization' => $row['organization'],
            'badges' => $row['badges'],
            'offersUrl' => $quoteRequest->signedOffersUrl(),
        ]);
    }

    public function offerAttachment(Request $request, string $locale, string $quoteRequest, string $suggestion): StreamedResponse
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest);
        $offer = $quoteRequest->offers->firstWhere('id', (int) $suggestion);
        abort_if($offer === null || ! $offer->attachment_path, 404);

        return self::downloadAttachment($offer);
    }

    public static function downloadAttachment(QuoteSuggestion $offer): StreamedResponse
    {
        abort_unless(Storage::exists($offer->attachment_path), 404);

        $extension = pathinfo($offer->attachment_path, PATHINFO_EXTENSION);

        return Storage::download(
            $offer->attachment_path,
            'findex-offer-'.$offer->id.($extension ? '.'.$extension : ''),
        );
    }

    public function selectOffer(Request $request, string $locale, string $quoteRequest, string $suggestion): RedirectResponse
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest);
        abort_unless($quoteRequest->is_open, 410);
        $offer = $quoteRequest->offers->firstWhere('id', (int) $suggestion);
        abort_if($offer === null, 404);
        abort_unless($offer->is_selectable, 410);
        $quoteRequest->offers()->whereNotNull('selected_at')->update(['selected_at' => null]);
        $offer->select();

        return redirect()
            ->to(SafeRedirectUrl::resolve($request, $request->headers->get('referer'), $quoteRequest->signedOffersUrl()))
            ->with('status', 'offer-selected');
    }

    public function close(Request $request, string $locale, string $quoteRequest): RedirectResponse
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest);

        if ($quoteRequest->is_open) {
            $quoteRequest->close();
        }

        return redirect()->to($quoteRequest->signedResultsUrl())->with('status', 'request-closed');
    }

    private function accessibleRequest(Request $request, string $id, bool $withProgress = false): QuoteRequest
    {
        $quoteRequest = QuoteRequest::query()
            ->with([
                'responses.organization' => fn ($query) => $query->withRatingStats(),
                'responses.suggestions',
            ])
            ->when($withProgress, fn ($query) => $query->withProgressCounts())
            ->findOrFail($id);

        $isOwner = $request->user() && $request->user()->id === $quoteRequest->user_id;

        abort_unless($isOwner || $request->hasValidSignature(), 403);

        return $quoteRequest;
    }

    public function claimSuggestion(Request $request, string $locale, string $quoteRequest, string $suggestion): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $quoteRequest = QuoteRequest::findOrFail($quoteRequest);

        $suggestion = QuoteSuggestion::whereHas(
            'response',
            fn ($query) => $query->where('quote_request_id', $quoteRequest->id)
        )->findOrFail($suggestion);

        abort_unless($suggestion->promo_code, 404);

        if (! $suggestion->is_claimed) {
            $suggestion->claim($request->user());
            app(PartnerNotifierInterface::class)->notifyClaim($suggestion);
        }

        return redirect()->route('tourism.show', $quoteRequest)->with('status', 'promo-claimed');
    }

    public function unsubscribeFromReviewPrompts(Request $request, string $locale): View
    {
        User::findOrFail($request->query('user'))->optOutOfReviewPrompts();

        return view('tourism.review-prompts-unsubscribed');
    }
}
