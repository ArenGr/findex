<?php

namespace App\Http\Controllers;

use App\Mail\AutoInsuranceQuoteInterest;
use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;
use App\Services\Insurance\AutoInsuranceQuoteService;
use App\Services\Insurance\InsuranceQuoteInputException;
use App\Services\Insurance\MarketQuoteDetails;
use App\Services\Insurance\QuoteIdentity;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AutoInsuranceController extends Controller
{
    public function create(): View
    {
        return view('insurance.auto.request', [
            'contractTerms' => AutoInsuranceRequest::CONTRACT_TERMS,
            // Shown in the loading screen's "checking N insurers" line.
            'insurerCount' => Organization::active()->where('type', 'insurance')->count(),
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
            'owner_id_number' => ['required', 'string', 'max:20'],
            'contract_term_months' => ['required', 'integer', Rule::in(AutoInsuranceRequest::CONTRACT_TERMS)],
            'guest_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'min:2', 'max:60'],
            'guest_email' => [Rule::requiredIf(! $request->user()), 'nullable', ValidationRules::email(), 'max:255'],
            'consent' => ['accepted'],
            // Required, not optional: quotes come from Sil's calculator alone
            // (see AutoInsuranceQuoteService), and it will not price anything
            // without a phone, an email and a bank account whose code it
            // recognises - so without these there is nothing to quote. The
            // bank account never leaves this request; see MarketQuoteDetails.
            'market_phone' => ['required', 'string', 'max:30'],
            'market_email' => ['required', ValidationRules::email(), 'max:255'],
            'market_bank_account' => ['required', 'digits_between:12,16'],
        ], attributes: [
            'guest_name' => __('tourism.request.your_name'),
            'guest_email' => __('tourism.request.your_email'),
            'market_phone' => __('auto_insurance.request.market_phone'),
            'market_email' => __('auto_insurance.request.market_email'),
            'market_bank_account' => __('auto_insurance.request.market_bank_account'),
        ]);

        // Both of these stop here. The ID number is what the insurers price
        // against and the bank account is what Sil's calculator demands, but
        // nothing on this side ever reads either back, so they are carried
        // for the length of this request and never written to a column -
        // see QuoteIdentity and MarketQuoteDetails.
        $marketDetails = new MarketQuoteDetails(
            phone: $validated['market_phone'],
            email: $validated['market_email'],
            bankAccountNumber: $validated['market_bank_account'],
        );

        $identity = new QuoteIdentity(
            plateNumber: $validated['vehicle_plate'],
            idNumber: $validated['owner_id_number'],
        );

        try {
            // Wrapped so that identifiers an insurer refuses leave nothing
            // behind: without this, a mistyped ID number would still have
            // created the request row before the first provider rejected it.
            $autoInsuranceRequest = DB::transaction(function () use ($request, $validated, $identity, $marketDetails, $quoteService) {
                $autoInsuranceRequest = AutoInsuranceRequest::create([
                    'user_id' => $request->user()?->id,
                    'guest_name' => $request->user() ? null : $validated['guest_name'],
                    'guest_email' => $request->user() ? null : $validated['guest_email'],
                    'locale' => app()->getLocale(),
                    // Stored upper-cased so the results summary matches the
                    // canonical plate the Bureau accepts (see QuoteIdentity).
                    'vehicle_plate' => mb_strtoupper(trim($validated['vehicle_plate'])),
                    // Rating inputs (owner type, engine power, driver
                    // experience, accident-free years) were dropped from the
                    // request form to keep the intake to plate/ID/term -
                    // owner_type still has to be one of
                    // AutoInsuranceRequest::OWNER_TYPES since the column
                    // isn't nullable, so it's fixed to 'individual' rather
                    // than asked for.
                    'owner_type' => 'individual',
                    'contract_term_months' => $validated['contract_term_months'],
                ]);

                $quoteService->requestQuotes($autoInsuranceRequest, $identity, $marketDetails);

                return $autoInsuranceRequest;
            });
        } catch (InsuranceQuoteInputException $e) {
            // Sil's own wording, in the user's own language - more specific
            // than anything written here, and it could be about any of the
            // inputs it validates (the ID against the vehicle owner, the
            // email format, the bank account's code), so it goes to a general
            // error at the top of the form rather than pinned to one field.
            //
            // except() rather than a bare withInput(): flashing the input
            // back would otherwise put the ID number and the bank account in
            // the session, the two places they must never end up.
            return back()
                ->withInput($request->except(['owner_id_number', 'market_bank_account']))
                ->withErrors(['insurance_quote' => $e->getMessage()]);
        }

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

        if (! $quote->is_interested) {
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
