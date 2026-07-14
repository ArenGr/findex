<?php

namespace App\Http\Controllers;

use App\Jobs\SendQuoteRequestToPartnersJob;
use App\Mail\QuoteRequestLinkResent;
use App\Mail\QuoteRequestSubmitted;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Models\QuoteSuggestion;
use App\Services\CurrencyConverter;
use App\Services\Notifications\PartnerNotifierInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuoteRequestController extends Controller
{
    public function create(): View
    {
        return view('tourism.request', [
            'destinations' => QuoteRequest::DESTINATIONS,
        ]);
    }

    /**
     * The only place a signed-in user can see every quote request they've
     * ever filed - without this, being logged in bought nothing over a
     * guest submission (both would rely on chasing down old emails).
     */
    public function mine(Request $request): View
    {
        $quoteRequests = $request->user()->quoteRequests()
            ->withCount([
                'responses',
                'responses as replied_responses_count' => fn ($query) => $query->whereNotNull('responded_at'),
            ])
            ->latest()
            ->get();

        return view('tourism.mine', ['quoteRequests' => $quoteRequests]);
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
            'email' => ['required', 'email', 'max:255'],
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

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: a real visitor never sees or fills this field (hidden via
        // CSS in the form). A bot filling every input trips it. Pretend to
        // succeed so it doesn't learn the check exists.
        if ($request->filled('company')) {
            return redirect()->route('tourism.request');
        }

        $validated = $request->validate([
            'destination_country' => ['required', 'string', Rule::in(QuoteRequest::DESTINATIONS)],
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'all_inclusive' => ['nullable', 'boolean'],
            'insurance' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'budget_min_amd' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'budget_max_amd' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'guest_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'min:2', 'max:60'],
            'guest_email' => [Rule::requiredIf(! $request->user()), 'nullable', 'email', 'max:255'],
            'consent' => ['accepted'],
        ], attributes: [
            'guest_name' => __('tourism.request.your_name'),
            'guest_email' => __('tourism.request.your_email'),
        ]);

        // Validated separately rather than via a `gte:budget_min_amd` rule -
        // that rule's handling of "the other field wasn't submitted at all"
        // (both are optional here) is ambiguous enough not to trust blindly.
        if (isset($validated['budget_min_amd'], $validated['budget_max_amd']) && $validated['budget_max_amd'] < $validated['budget_min_amd']) {
            return back()->withInput()->withErrors([
                'budget_max_amd' => __('tourism.request.budget_max_below_min'),
            ]);
        }

        $partySize = $validated['adults'] + ($validated['children'] ?? 0);
        $budgetForFiltering = $validated['budget_max_amd'] ?? $validated['budget_min_amd'] ?? null;

        $partners = Organization::tourismPartnersForDestination(
            $validated['destination_country'],
            $partySize,
            $budgetForFiltering !== null ? (float) $budgetForFiltering : null,
        )->get();

        if ($partners->isEmpty()) {
            return back()->withInput()->withErrors([
                'destination_country' => __('tourism.request.no_partners_for_destination'),
            ]);
        }

        $quoteRequest = QuoteRequest::create([
            'user_id' => $request->user()?->id,
            'guest_name' => $request->user() ? null : $validated['guest_name'],
            'guest_email' => $request->user() ? null : $validated['guest_email'],
            'locale' => app()->getLocale(),
            'destination_country' => $validated['destination_country'],
            'hotel_name' => $validated['hotel_name'] ?? null,
            'check_in' => $validated['check_in'],
            'check_out' => $validated['check_out'],
            'adults' => $validated['adults'],
            'children' => $validated['children'] ?? 0,
            'all_inclusive' => $request->boolean('all_inclusive'),
            'insurance' => $request->boolean('insurance'),
            'notes' => $validated['notes'] ?? null,
            'budget_min_amd' => $validated['budget_min_amd'] ?? null,
            'budget_max_amd' => $validated['budget_max_amd'] ?? null,
            'expires_at' => now()->addDays(14),
        ]);

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
     * Resolved manually rather than via implicit route-model binding to keep
     * the same convention as Organization\BranchController - and because
     * access here is also gated on either being the owning user or holding a
     * valid signed link, which implicit binding can't express.
     */
    public function show(Request $request, string $locale, string $quoteRequest, CurrencyConverter $currencyConverter): View
    {
        $quoteRequest = QuoteRequest::with(['responses.organization', 'responses.suggestions'])->findOrFail($quoteRequest);

        $isOwner = $request->user() && $request->user()->id === $quoteRequest->user_id;

        abort_unless($isOwner || $request->hasValidSignature(), 403);

        return view('tourism.show', [
            'quoteRequest' => $quoteRequest,
            'preferredCurrency' => $currencyConverter->preferredCurrencyForLocale(app()->getLocale()),
            'currencyConverter' => $currencyConverter,
        ]);
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

        if (!$suggestion->is_claimed) {
            $suggestion->claim($request->user());
            app(PartnerNotifierInterface::class)->notifyClaim($suggestion);
        }

        return redirect()->route('tourism.show', $quoteRequest)->with('status', 'promo-claimed');
    }
}
