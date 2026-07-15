<?php

namespace App\Http\Controllers;

use App\Mail\AutoInsuranceQuoteInterest;
use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Services\Insurance\AutoInsuranceQuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AutoInsuranceController extends Controller
{
    public function create(): View
    {
        return view('insurance.auto.request', [
            'ownerTypes' => AutoInsuranceRequest::OWNER_TYPES,
            'contractTerms' => AutoInsuranceRequest::CONTRACT_TERMS,
        ]);
    }

    public function store(Request $request, AutoInsuranceQuoteService $quoteService): RedirectResponse
    {
        // Honeypot: see QuoteRequestController::store for why this is
        // silently ignored rather than rejected outright.
        if ($request->filled('company')) {
            return redirect()->route('insurance.auto.request');
        }

        $validated = $request->validate([
            'vehicle_plate' => ['required', 'string', 'max:20'],
            'owner_type' => ['required', 'string', Rule::in(AutoInsuranceRequest::OWNER_TYPES)],
            'owner_id_number' => ['required', 'string', 'max:20'],
            'contract_term_months' => ['required', 'integer', Rule::in(AutoInsuranceRequest::CONTRACT_TERMS)],
            'engine_power_hp' => ['required', 'integer', 'min:1', 'max:800'],
            'driver_experience_years' => ['required', 'integer', 'min:0', 'max:70'],
            'accident_free_years' => ['required', 'integer', 'min:0', 'lte:driver_experience_years'],
            'guest_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'min:2', 'max:60'],
            'guest_email' => [Rule::requiredIf(! $request->user()), 'nullable', 'email', 'max:255'],
            'consent' => ['accepted'],
        ], attributes: [
            'guest_name' => __('tourism.request.your_name'),
            'guest_email' => __('tourism.request.your_email'),
            'engine_power_hp' => __('auto_insurance.request.engine_power'),
            'driver_experience_years' => __('auto_insurance.request.driver_experience'),
            'accident_free_years' => __('auto_insurance.request.accident_free_years'),
        ]);

        $autoInsuranceRequest = AutoInsuranceRequest::create([
            'user_id' => $request->user()?->id,
            'guest_name' => $request->user() ? null : $validated['guest_name'],
            'guest_email' => $request->user() ? null : $validated['guest_email'],
            'locale' => app()->getLocale(),
            'vehicle_plate' => $validated['vehicle_plate'],
            'owner_type' => $validated['owner_type'],
            'owner_id_number' => $validated['owner_id_number'],
            'contract_term_months' => $validated['contract_term_months'],
            'engine_power_hp' => $validated['engine_power_hp'],
            'driver_experience_years' => $validated['driver_experience_years'],
            'accident_free_years' => $validated['accident_free_years'],
        ]);

        $quoteService->requestQuotes($autoInsuranceRequest);

        return ($request->user()
            ? redirect()->route('insurance.auto.show', $autoInsuranceRequest)
            : redirect($autoInsuranceRequest->signedResultsUrl()))->with('status', 'insurance-request-submitted');
    }

    /**
     * Resolved manually rather than via implicit route-model binding to keep
     * the same convention as QuoteRequestController::show - access is gated
     * on either being the owning user or holding a valid signed link, which
     * implicit binding can't express.
     */
    public function show(Request $request, string $locale, string $autoInsuranceRequest): View
    {
        $autoInsuranceRequest = AutoInsuranceRequest::with(['quotes.organization'])->findOrFail($autoInsuranceRequest);

        $isOwner = $request->user() && $request->user()->id === $autoInsuranceRequest->user_id;

        abort_unless($isOwner || $request->hasValidSignature(), 403);

        return view('insurance.auto.show', ['autoInsuranceRequest' => $autoInsuranceRequest]);
    }

    /**
     * No auth required - the signed URL itself (same no-expiry pattern as
     * AutoInsuranceRequest::signedResultsUrl(), minted fresh on the
     * already-gated results page) is the credential, matching how a guest
     * can file the request in the first place. There's no identity to
     * protect here the way a claimed promo code has - just an interest
     * signal - so unlike QuoteRequestController::claimSuggestion this
     * doesn't need to be limited to logged-in accounts.
     */
    public function markInterested(Request $request, string $locale, string $autoInsuranceRequest, string $quote): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $autoInsuranceRequest = AutoInsuranceRequest::findOrFail($autoInsuranceRequest);

        $quote = AutoInsuranceQuote::where('auto_insurance_request_id', $autoInsuranceRequest->id)
            ->findOrFail($quote);

        abort_if($quote->is_declined, 404);

        if (!$quote->is_interested) {
            $quote->markInterested();
            $quote->load('organization.users', 'autoInsuranceRequest');

            $recipients = $quote->organization->users->pluck('email');
            if ($recipients->isNotEmpty()) {
                Mail::to($recipients)->send(new AutoInsuranceQuoteInterest($quote));
            }
        }

        // A plain route() redirect would 403 a guest here - show() requires
        // either ownership or a valid signature, and a guest has neither
        // without the signed link (see AutoInsuranceRequest::signedResultsUrl()).
        return redirect($autoInsuranceRequest->signedResultsUrl())->with('status', 'interest-marked');
    }
}
