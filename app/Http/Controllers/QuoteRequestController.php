<?php

namespace App\Http\Controllers;

use App\Jobs\SendQuoteRequestToPartnersJob;
use App\Mail\QuoteRequestLinkResent;
use App\Mail\QuoteRequestSubmitted;
use App\Models\Organization;
use App\Models\QuoteRequest;
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
            'guest_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'min:2', 'max:60'],
            'guest_email' => [Rule::requiredIf(! $request->user()), 'nullable', 'email', 'max:255'],
            'consent' => ['accepted'],
        ], attributes: [
            'guest_name' => __('tourism.request.your_name'),
            'guest_email' => __('tourism.request.your_email'),
        ]);

        $partners = Organization::active()
            ->where('type', 'tourism')
            ->whereNotNull('telegram_chat_id')
            ->whereHas('tourismDestinations', fn ($query) => $query->where(
                'country_code',
                $validated['destination_country']
            ))
            ->get();

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
            : redirect($resultsUrl))->with('status', 'quote-request-submitted');
    }

    /**
     * Resolved manually rather than via implicit route-model binding to keep
     * the same convention as Organization\BranchController - and because
     * access here is also gated on either being the owning user or holding a
     * valid signed link, which implicit binding can't express.
     */
    public function show(Request $request, string $locale, string $quoteRequest): View
    {
        $quoteRequest = QuoteRequest::with(['responses.organization'])->findOrFail($quoteRequest);

        $isOwner = $request->user() && $request->user()->id === $quoteRequest->user_id;

        abort_unless($isOwner || $request->hasValidSignature(), 403);

        return view('tourism.show', ['quoteRequest' => $quoteRequest]);
    }
}
