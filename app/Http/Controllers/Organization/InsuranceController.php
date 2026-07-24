<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class InsuranceController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        // Cached (TTL-only, no tags): viewed only by this org's own staff,
        // so a few minutes of staleness after a new lead/interest-mark is
        // harmless. Flattened to a plain array rather than the raw
        // quote+request models - config/cache.php's 'serializable_classes'
        // => false means Redis can't round-trip objects, and this also
        // sidesteps AutoInsuranceRequest::requester_name/_email lazy-loading
        // the user relation per row on every uncached view. Includes
        // requester name/phone/plate (PII) - acceptable since Redis here is
        // purely an internal, non-exposed cache, but a heavier/more
        // sensitive entry than the scalar org stats above.
        $data = Cache::remember("org.{$organization->id}.insurance_dashboard", now()->addMinutes(10), function () use ($organization) {
            $quotes = $organization->autoInsuranceQuotes()
                ->with('autoInsuranceRequest.user')
                ->latest()
                ->get();

            $interestedCount = $quotes->filter(fn ($quote) => $quote->is_interested)->count();

            return [
                'quotes' => $quotes->map(fn ($quote) => [
                    'vehicle_plate' => $quote->autoInsuranceRequest->vehicle_plate,
                    'policy_term_months' => $quote->policy_term_months,
                    'created_at' => $quote->created_at->toIso8601String(),
                    'requester_name' => $quote->autoInsuranceRequest->requester_name,
                    'requester_email' => $quote->autoInsuranceRequest->requester_email,
                    'premium_amount' => $quote->premium_amount,
                    'premium_currency' => $quote->premium_currency,
                    'is_interested' => $quote->is_interested,
                    'interested_at' => $quote->interested_at?->toIso8601String(),
                ])->all(),
                'totalQuotes' => $quotes->count(),
                'interestedCount' => $interestedCount,
                'interestedRate' => $quotes->isNotEmpty() ? round($interestedCount / $quotes->count() * 100) : null,
            ];
        });

        return view('organizations.dashboard.insurance.index', [
            'organization' => $organization,
            'quotes' => collect($data['quotes'])->map(fn (array $row) => (object) $row),
            'totalQuotes' => $data['totalQuotes'],
            'interestedCount' => $data['interestedCount'],
            'interestedRate' => $data['interestedRate'],
        ]);
    }
}
