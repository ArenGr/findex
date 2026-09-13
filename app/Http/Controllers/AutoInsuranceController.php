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

        // Both of these stop here.
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
            $autoInsuranceRequest = DB::transaction(function () use ($request, $validated, $identity, $marketDetails, $quoteService) {
                $autoInsuranceRequest = AutoInsuranceRequest::create([
                    'user_id' => $request->user()?->id,
                    'guest_name' => $request->user() ? null : $validated['guest_name'],
                    'guest_email' => $request->user() ? null : $validated['guest_email'],
                    'locale' => app()->getLocale(),
                    'vehicle_plate' => mb_strtoupper(trim($validated['vehicle_plate'])),
                    'owner_type' => 'individual',
                    'contract_term_months' => $validated['contract_term_months'],
                ]);

                $quoteService->requestQuotes($autoInsuranceRequest, $identity, $marketDetails);

                return $autoInsuranceRequest;
            });
        } catch (InsuranceQuoteInputException $e) {
            return back()
                ->withInput($request->except(['owner_id_number', 'market_bank_account']))
                ->withErrors(['insurance_quote' => $e->getMessage()]);
        }

        return ($request->user()
            ? redirect()->route('insurance.auto.show', $autoInsuranceRequest)
            : redirect($autoInsuranceRequest->signedResultsUrl()))->with('status', 'insurance-request-submitted');
    }

    // How the results page may be ordered.
    public const SORTS = ['price', 'price_desc', 'rating'];

    public function show(Request $request, string $locale, string $autoInsuranceRequest): View
    {
        $autoInsuranceRequest = AutoInsuranceRequest::with([
            'quotes.organization' => fn ($query) => $query->withRatingStats(),
        ])->findOrFail($autoInsuranceRequest);

        $isOwner = $request->user() && $request->user()->id === $autoInsuranceRequest->user_id;

        abort_unless($isOwner || $request->hasValidSignatureWhileIgnoring(['sort']), 403);

        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'price';

        return view('insurance.auto.show', [
            'autoInsuranceRequest' => $autoInsuranceRequest,
            'sort' => $sort,
        ]);
    }

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

        return redirect($autoInsuranceRequest->signedResultsUrl())->with('status', 'interest-marked');
    }
}
