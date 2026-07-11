<?php

namespace App\Http\Controllers;

use App\Models\AutoInsuranceRequest;
use App\Services\Insurance\AutoInsuranceQuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'guest_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'min:2', 'max:60'],
            'guest_email' => [Rule::requiredIf(! $request->user()), 'nullable', 'email', 'max:255'],
            'consent' => ['accepted'],
        ], attributes: [
            'guest_name' => __('tourism.request.your_name'),
            'guest_email' => __('tourism.request.your_email'),
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
}
