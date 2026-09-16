<?php

namespace App\Http\Controllers;

use App\Enums\VisaRequestStatus;
use App\Jobs\SendVisaRequestToPartnersJob;
use App\Mail\VisaRequestSubmitted;
use App\Models\Organization;
use App\Models\VisaRequest;
use App\Models\VisaResponse;
use App\Support\TravelPartners;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\Intl\Countries;

class VisaRequestController extends Controller
{
    public function create(): View
    {
        return view('visa.request', [
            'countries' => $this->worldCountries(),
            'maxApplicants' => VisaRequest::MAX_APPLICANTS,
            // Presentation only - the "trusted by" strip.
            'partners' => TravelPartners::forType('visa'),
        ]);
    }

    /**
     * @return array<int, array{code: string, name: string, flag: string}>
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
     * @return array<string, array<int, mixed>>
     */
    private function rules(Request $request): array
    {
        return [
            'destination_country' => ['required', 'string', Rule::in(array_keys(Countries::getNames()))],
            'travel_from' => ['required', 'date', 'after_or_equal:today'],
            'travel_to' => ['required', 'date', 'after_or_equal:travel_from'],
            'applicants' => ['required', 'integer', 'min:1', 'max:'.VisaRequest::MAX_APPLICANTS],
            'guest_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'min:2', 'max:60'],
            'guest_email' => [Rule::requiredIf(! $request->user()), 'nullable', ValidationRules::email(), 'max:255'],
            'consent' => ['accepted'],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: hidden from real visitors, so anything here is a bot.
        if ($request->filled('company')) {
            return redirect()->route('visa.request');
        }

        if ($request->user() && ! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('visa.request')->with('status', 'email-verification-required');
        }

        $validated = $request->validate($this->rules($request), attributes: [
            'guest_name' => __('visa.request.your_name'),
            'guest_email' => __('visa.request.your_email'),
        ]);

        $visaRequest = new VisaRequest([
            'user_id' => $request->user()?->id,
            'guest_name' => $request->user() ? null : $validated['guest_name'],
            'guest_email' => $request->user() ? null : $validated['guest_email'],
            'locale' => app()->getLocale(),
            'destination_country' => $validated['destination_country'],
            'travel_from' => $validated['travel_from'],
            'travel_to' => $validated['travel_to'],
            'applicants' => (int) $validated['applicants'],
            'status' => VisaRequestStatus::SUBMITTED,
            'expires_at' => now()->addDays(VisaRequest::DAYS_OPEN),
        ]);

        // Nobody to ask is a dead end, so it is caught before anything is stored.
        $partners = Organization::visaPartners()->get();
        if ($partners->isEmpty()) {
            return back()->withInput()->withErrors([
                'destination_country' => __('visa.request.no_partners'),
            ]);
        }

        if ($existing = $this->existingOpenRequest($request, $validated)) {
            return redirect()->route('visa.show', $existing)->with('status', 'visa-request-duplicate');
        }

        $visaRequest->save();

        SendVisaRequestToPartnersJob::dispatch($visaRequest);

        $resultsUrl = $visaRequest->signedResultsUrl();

        if ($visaRequest->requester_email) {
            Mail::to($visaRequest->requester_email)
                ->locale($visaRequest->locale)
                ->send(new VisaRequestSubmitted($visaRequest, $resultsUrl, $partners->count()));
        }

        return ($request->user()
            ? redirect()->route('visa.show', $visaRequest)
            : redirect($resultsUrl))->with([
                'status' => 'visa-request-submitted',
                'contacted_count' => $partners->count(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function existingOpenRequest(Request $request, array $validated): ?VisaRequest
    {
        return VisaRequest::query()
            ->when(
                $request->user(),
                fn ($query) => $query->where('user_id', $request->user()->id),
                fn ($query) => $query->whereNull('user_id')->where('guest_email', $validated['guest_email']),
            )
            ->where('destination_country', $validated['destination_country'])
            ->whereDate('travel_from', $validated['travel_from'])
            ->whereDate('travel_to', $validated['travel_to'])
            ->open()
            ->first();
    }

    public function show(Request $request, string $locale, string $visaRequest): View
    {
        $visaRequest = $this->accessibleRequest($request, $visaRequest);

        // Cheapest first, then the agencies still to answer.
        $offers = $visaRequest->responses
            ->where('status', VisaResponse::STATUS_RESPONDED)
            ->sortBy(fn (VisaResponse $response) => (float) $response->price_amount)
            ->values();

        return view('visa.show', [
            'visaRequest' => $visaRequest,
            'offers' => $offers,
            'pending' => $visaRequest->responses
                ->where('status', VisaResponse::STATUS_PENDING)
                ->values(),
        ]);
    }

    public function close(Request $request, string $locale, string $visaRequest): RedirectResponse
    {
        $visaRequest = $this->accessibleRequest($request, $visaRequest);

        $visaRequest->close();

        return back()->with('status', 'visa-request-closed');
    }

    // The owner, or anyone holding the signed link we emailed.
    private function accessibleRequest(Request $request, string $id): VisaRequest
    {
        $visaRequest = VisaRequest::query()
            ->with(['responses.organization'])
            ->findOrFail($id);

        $isOwner = $request->user() && $visaRequest->user_id === $request->user()->id;

        abort_unless($isOwner || $request->hasValidSignature(), 403);

        return $visaRequest;
    }
}
