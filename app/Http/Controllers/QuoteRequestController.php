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
    /**
     * Below this sample size, an "average" would be one or two agencies'
     * prices dressed up as a market figure - not a real teaser, and a
     * de-anonymization risk on top.
     */
    private const TYPICAL_PRICE_MIN_SUGGESTIONS = 3;

    private const TYPICAL_PRICE_MIN_ORGS = 2;

    /**
     * Currencies a budget may be stated in besides AMD. The request form
     * itself now asks for an AMD band (see QuoteRequest::BUDGET_BANDS), so
     * nothing on the page submits these - they stay accepted because
     * budget_currency is a real column any other caller may set, and
     * silently rejecting a valid currency would be worse than allowing one
     * the form happens not to offer.
     */
    private const BUDGET_CURRENCIES = ['USD', 'EUR', 'RUR'];

    /**
     * How many offers the side-by-side view will line up at once. Four
     * columns is what stays readable on a desktop screen without each one
     * becoming too narrow to read a hotel name in; past that the comparison
     * stops being a comparison and becomes a spreadsheet.
     *
     * Public because the offers page enforces the same cap on its own
     * selection controls - two different numbers here would let a traveler
     * pick five offers and silently lose one on the way.
     */
    public const MAX_COMPARED_OFFERS = 4;

    public function create(TourismPriceData $priceData): View
    {
        return view('tourism.request', [
            'destinations' => QuoteRequest::DESTINATIONS,
            'countries' => $this->worldCountries(),
            'typicalPrices' => $this->typicalPrices($priceData),
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
        ]);
    }

    /**
     * Turns one of QuoteRequest's option lists into the value => label map
     * the chip groups render from, keeping the option order defined on the
     * model rather than re-stated in the view.
     *
     * @param  array<int, string>  $values
     * @return array<string, string>
     */
    private static function labelled(array $values, string $keyPrefix): array
    {
        return collect($values)->mapWithKeys(fn ($value) => [$value => __($keyPrefix.$value)])->all();
    }

    /**
     * Every ISO-3166 country, translated and flagged, for the destination
     * picker - not just QuoteRequest::DESTINATIONS (the curated set org
     * dashboards can mark themselves as serving). Picking a country with no
     * active partner today isn't blocked here; store() below still checks
     * live partner availability and offers the "notify me" fallback for
     * anything unmatched, exactly the same as it already did for a
     * DESTINATIONS-listed country nobody currently serves. The flag emoji
     * is derived from the ISO code itself (regional indicator symbols are
     * just the two letters shifted into a Unicode block), not a hardcoded
     * lookup table, so it's correct for any of the ~249 entries for free.
     */
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

    /**
     * A rough "what does this usually cost" figure shown on the request
     * form itself, before a visitor commits to filling it out - built from
     * the same historical, already-responded suggestion data as the org
     * dashboard's price benchmark (see
     * Organization\TourismController::priceBenchmark), just aggregated
     * across every organization instead of one. Destinations without
     * enough sample size are simply omitted (null) rather than shown with
     * a misleadingly precise figure.
     */
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

    /**
     * The only place a signed-in user can see every quote request they've
     * ever filed - without this, being logged in bought nothing over a
     * guest submission (both would rely on chasing down old emails).
     */
    public function mine(Request $request): View
    {
        // Two tabs, not five: "can this still receive offers or not" is the
        // only split that changes what a traveler would do next. Anything
        // finer is filtering for its own sake at this stage.
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

    /**
     * A guest has no account to log back into, so a lost confirmation email
     * means a lost results link with no way back - this re-sends it. The
     * response is identical whether or not a match is found, so this can't
     * be used to check which email addresses have filed a request.
     */
    public function resend(Request $request): RedirectResponse
    {
        // Honeypot: see QuoteRequestController::store for why this is
        // silently ignored rather than rejected outright.
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

    /**
     * Extracted from store() only for length - this stays an inline
     * $request->validate() call rather than a FormRequest, matching how
     * every other controller in this app validates.
     */
    private function rules(Request $request): array
    {
        return [
            'departure_location' => ['required', 'string', 'max:120'],

            // Any real country, not just QuoteRequest::DESTINATIONS - the
            // partner-availability check in store() is what actually gates
            // whether these destinations can be fulfilled today. Optional
            // only because "open to suggestions" is a valid answer instead;
            // the two are cross-checked in store().
            'destination_countries' => ['nullable', 'array', 'max:'.QuoteRequest::MAX_DESTINATIONS],
            'destination_countries.*' => ['distinct', Rule::in(array_keys(Countries::getNames()))],
            'open_to_suggestions' => ['nullable', 'boolean'],

            'hotel_name' => ['nullable', 'string', 'max:255'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            // Absent means exact dates; the form only offers the three
            // windows, so an arbitrary value isn't accepted here either.
            'date_flexibility' => ['nullable', Rule::in(QuoteRequest::DATE_FLEXIBILITY_OPTIONS)],

            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:'.QuoteRequest::MAX_CHILDREN],
            // One age per child, checked against the stated count in
            // store() - a rule here can't see the other field reliably
            // enough to be worth trusting.
            'child_ages' => ['nullable', 'array', 'max:'.QuoteRequest::MAX_CHILDREN],
            'child_ages.*' => ['required', 'integer', 'min:0', 'max:'.QuoteRequest::MAX_CHILD_AGE],
            // Nullable rather than required: the form always submits all
            // three (each opens on a default chip), and an omitted one has
            // exactly one sensible reading - "flexible" / "any" - which is
            // the column default it falls back to below. Requiring them
            // would reject that reading without making anything safer.
            // A value that IS sent still has to be one the form offers.
            'flight_preference' => ['nullable', Rule::in(QuoteRequest::FLIGHT_PREFERENCES)],
            'hotel_preference' => ['nullable', Rule::in(QuoteRequest::HOTEL_PREFERENCES)],
            'meal_preference' => ['nullable', Rule::in(QuoteRequest::MEAL_PREFERENCES)],
            // distinct as well as capped: three copies of "lowest price"
            // would pass a bare max:3 and tell an agency nothing.
            'priorities' => ['nullable', 'array', 'max:'.QuoteRequest::MAX_PRIORITIES],
            'priorities.*' => ['distinct', Rule::in(QuoteRequest::PRIORITIES)],
            'insurance' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            // What the form actually asks for. The explicit min/max below
            // stay valid for anything submitting a figure rather than a
            // band; a band, when given, wins (see budgetBounds()).
            'budget_band' => ['nullable', Rule::in(array_keys(QuoteRequest::BUDGET_BANDS))],
            'budget_min_amd' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'budget_max_amd' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            // Display only (the stored figures are always AMD - see the
            // budget_currency column), but still checked against the list
            // the form actually offers rather than any three letters.
            'budget_currency' => ['nullable', Rule::in(self::budgetCurrencyCodes())],
            'guest_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'min:2', 'max:60'],
            'guest_email' => [Rule::requiredIf(! $request->user()), 'nullable', ValidationRules::email(), 'max:255'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * Every currency the budget control can be switched into, AMD included
     * - the same set budgetCurrencyRates() builds, minus the live rates,
     * so validation can't drift from what the form offers.
     *
     * @return array<int, string>
     */
    private static function budgetCurrencyCodes(): array
    {
        return array_merge(['AMD'], self::BUDGET_CURRENCIES);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a real visitor never sees or fills this field (hidden via
        // CSS in the form). A bot filling every input trips it. Pretend to
        // succeed so it doesn't learn the check exists.
        if ($request->filled('company')) {
            return redirect()->route('tourism.request');
        }

        // Guests remain unaffected (no account, nothing to verify) - this
        // only blocks a logged-in customer whose own account email isn't
        // confirmed yet, since replies and the results link both depend on
        // that address actually being reachable.
        if ($request->user() && ! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('tourism.request')->with('status', 'email-verification-required');
        }

        $validated = $request->validate($this->rules($request), attributes: [
            'guest_name' => __('tourism.request.your_name'),
            'guest_email' => __('tourism.request.your_email'),
        ]);

        $destinations = array_values($validated['destination_countries'] ?? []);
        $openToSuggestions = $request->boolean('open_to_suggestions');

        // A request has to say where it's going, or say that it doesn't
        // know - otherwise there is nothing for an agency to quote.
        if ($destinations === [] && ! $openToSuggestions) {
            return back()->withInput()->withErrors([
                'destination_countries' => __('tourism.request.destination_required'),
            ]);
        }

        $childAges = array_values($validated['child_ages'] ?? []);
        $children = $validated['children'] ?? 0;

        // An age per child, no more and no fewer. Checked here rather than
        // with a size: rule so the message can name the mismatch.
        if (count($childAges) !== $children) {
            return back()->withInput()->withErrors([
                'child_ages' => __('tourism.request.child_ages_required', ['count' => $children]),
            ]);
        }

        // Normalised here, once, so everything downstream - the min/max
        // check just below, the partner match, and the row itself - keeps
        // working in plain figures and never needs to know a band existed.
        $validated = $this->applyBudgetBand($validated);

        // Validated separately rather than via a `gte:budget_min_amd` rule -
        // that rule's handling of "the other field wasn't submitted at all"
        // (both are optional here) is ambiguous enough not to trust blindly.
        if (isset($validated['budget_min_amd'], $validated['budget_max_amd']) && $validated['budget_max_amd'] < $validated['budget_min_amd']) {
            return back()->withInput()->withErrors([
                'budget_max_amd' => __('tourism.request.budget_max_below_min'),
            ]);
        }

        // Built before it is saved, so the partner match below runs against
        // the real thing - the same scope the queued fan-out uses, rather
        // than a second copy of the matching rules that can drift from it.
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

        // Sets destination_countries and keeps destination_country pointing
        // at the first of them - see QuoteRequest::setDestinations().
        $quoteRequest->setDestinations($destinations);

        // Capped, and the same question the queued fan-out will ask.
        $partners = Organization::tourismPartnersForRequest($quoteRequest)->get();

        if ($partners->isEmpty()) {
            return back()->withInput()->withErrors([
                'destination_countries' => __('tourism.request.no_partners_for_destination'),
            ]);
        }

        // A double-tapped submit button (or a back-then-resubmit) would
        // otherwise fan the same trip out to every agency twice, and leave
        // the traveler comparing two identical request pages. Landing them
        // on the one they already have is the honest outcome - nothing was
        // lost, and the offers they're waiting for are all on it.
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
                // The real match count, known synchronously here - unlike
                // $quoteRequest->responses->count() on the results page,
                // which depends on the queued SendQuoteRequestToPartnersJob
                // having actually run by the time that page first loads.
                'contacted_count' => $partners->count(),
            ]);
    }

    /**
     * An open request from the same requester for the same trip - the same
     * destination and the same dates. Deliberately narrow: someone filing
     * two genuinely different trips to one country, or the same trip on
     * different dates, is doing something real and must not be blocked.
     * Matching on the trip itself rather than a time window also catches a
     * resubmit that arrives well after the first, which a "created in the
     * last N seconds" check would wave through.
     */
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

    /**
     * Turns a selected budget band into the pair of figures the rest of the
     * flow works in. A band always replaces any min/max submitted alongside
     * it: the form posts one or the other, and if something posts both, the
     * band is the one the traveler actually chose from.
     *
     * "Flexible" clears both, which reads downstream as no budget stated at
     * all - which is exactly what it means.
     */
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

    /**
     * Resolved manually rather than via implicit route-model binding to keep
     * the same convention as Organization\BranchController - and because
     * access here is also gated on either being the owning user or holding a
     * valid signed link, which implicit binding can't express.
     */
    public function show(Request $request, string $locale, string $quoteRequest): View
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest, withProgress: true);

        return view('tourism.show', [
            'quoteRequest' => $quoteRequest,
            // Minted here rather than in the view so a guest's links out of
            // this page carry a signature for the exact route they point at
            // (see QuoteRequest::signedUrlFor). An owner doesn't need them,
            // but a signed link works for them too, so there's no branch.
            'offersUrl' => $quoteRequest->signedOffersUrl(),
            'compareUrl' => $quoteRequest->signedUrlFor('tourism.compare'),
        ]);
    }

    /**
     * The offers themselves - what the "you have a new offer" email links
     * to, and what the status page's "view offers" leads to.
     */
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

    /**
     * The side-by-side view. Which offers to line up comes in on the query
     * string; anything that isn't an offer on this request is dropped
     * rather than erroring, since a stale or hand-edited link is far more
     * likely than an attack, and the page is perfectly able to show the
     * remaining valid ones.
     */
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

    /**
     * One offer in full, with the agency behind it. Deliberately not a
     * booking page - see the contact/choose action at the bottom of it.
     */
    public function offer(Request $request, string $locale, string $quoteRequest, string $suggestion, TravelOfferComparison $comparison): View
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest);

        $row = $comparison->for($quoteRequest)->firstWhere('offer.id', (int) $suggestion);

        // 404 rather than 403: an offer on somebody else's request is, from
        // here, simply not an offer that exists.
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

    /**
     * Downloads an offer's attachment.
     *
     * Exists so the file itself can live on the private disk: the same
     * owner-or-signature gate as every other page about this request, rather
     * than a public URL that works forever for anyone who obtains it.
     */
    public function offerAttachment(Request $request, string $locale, string $quoteRequest, string $suggestion): StreamedResponse
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest);

        $offer = $quoteRequest->offers->firstWhere('id', (int) $suggestion);

        // Scoped to this request, so an id belonging to someone else's offer
        // is simply not found here.
        abort_if($offer === null || ! $offer->attachment_path, 404);

        return self::downloadAttachment($offer);
    }

    /**
     * Streams a stored attachment under a readable filename - the stored
     * name is a random hash, which is not what anyone wants in their
     * downloads folder.
     */
    public static function downloadAttachment(QuoteSuggestion $offer): StreamedResponse
    {
        abort_unless(Storage::exists($offer->attachment_path), 404);

        $extension = pathinfo($offer->attachment_path, PATHINFO_EXTENSION);

        return Storage::download(
            $offer->attachment_path,
            'findex-offer-'.$offer->id.($extension ? '.'.$extension : ''),
        );
    }

    /**
     * Choosing an offer. This is the end of Findex's involvement - it
     * records which offer the traveler went with and tells the agency to
     * expect them. No payment, no reservation: the agency handles the
     * booking itself, off this platform.
     */
    public function selectOffer(Request $request, string $locale, string $quoteRequest, string $suggestion): RedirectResponse
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest);

        // 410 rather than 404 - the request is real, it just can't be acted
        // on any more. Same treatment as the exchange flow's accept().
        abort_unless($quoteRequest->is_open, 410);

        $offer = $quoteRequest->offers->firstWhere('id', (int) $suggestion);

        abort_if($offer === null, 404);

        // An expired offer is still shown, but the agency is no longer
        // holding that price, so it must not be choosable.
        abort_unless($offer->is_selectable, 410);

        // Changing your mind is allowed while the request is open, so any
        // previous pick drops back to being an ordinary offer rather than
        // leaving the traveler with two selected at once.
        $quoteRequest->offers()->whereNotNull('selected_at')->update(['selected_at' => null]);

        $offer->select();

        // Back where they were - but only if that is still this site. The
        // Referer is browser-supplied, so an unchecked one is an open
        // redirect (see SafeRedirectUrl).
        return redirect()
            ->to(SafeRedirectUrl::resolve($request, $request->headers->get('referer'), $quoteRequest->signedOffersUrl()))
            ->with('status', 'offer-selected');
    }

    /**
     * Ending the request early. Offers already received stay readable - the
     * traveler may still want the agency's contact details - this only
     * stops new ones arriving.
     */
    public function close(Request $request, string $locale, string $quoteRequest): RedirectResponse
    {
        $quoteRequest = $this->accessibleRequest($request, $quoteRequest);

        if ($quoteRequest->is_open) {
            $quoteRequest->close();
        }

        return redirect()->to($quoteRequest->signedResultsUrl())->with('status', 'request-closed');
    }

    /**
     * Resolves the request and checks the caller may see it - the owning
     * account, or a valid signed link. Every request-scoped page goes
     * through here rather than repeating the check, so one of them can't
     * quietly end up without it.
     */
    private function accessibleRequest(Request $request, string $id, bool $withProgress = false): QuoteRequest
    {
        $quoteRequest = QuoteRequest::query()
            ->with([
                // withRatingStats so the star rating and "top rated" badge on
                // each offer card come out of this one query rather than two
                // more per agency (see Organization::isTopRated()).
                'responses.organization' => fn ($query) => $query->withRatingStats(),
                'responses.suggestions',
            ])
            ->when($withProgress, fn ($query) => $query->withProgressCounts())
            ->findOrFail($id);

        $isOwner = $request->user() && $request->user()->id === $quoteRequest->user_id;

        abort_unless($isOwner || $request->hasValidSignature(), 403);

        return $quoteRequest;
    }

    /**
     * Claiming a promo code requires being logged in (so an org can verify,
     * in person, that whoever redeems the code is the same account that
     * claimed it - see QuoteSuggestion::claim()). The form action itself is
     * a signed URL freshly minted on the already-gated results page (see
     * tourism/show.blade.php), the same trust model as
     * QuoteRequest::signedResultsUrl() - without it, a logged-in customer
     * could guess another customer's (sequential) quote request id and
     * steal their promo code.
     */
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

    /**
     * Reached from the signed unsubscribe link in TripReviewPrompt's email
     * footer - see User::optOutOfReviewPrompts() and the exclusion in
     * PromptTripReviews.
     */
    public function unsubscribeFromReviewPrompts(Request $request, string $locale): View
    {
        User::findOrFail($request->query('user'))->optOutOfReviewPrompts();

        return view('tourism.review-prompts-unsubscribed');
    }
}
