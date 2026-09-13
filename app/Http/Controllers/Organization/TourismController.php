<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Jobs\BackfillOpenRequestsToNewPartnerJob;
use App\Jobs\NotifyDestinationAlertsJob;
use App\Models\Organization;
use App\Models\QuoteRequest;
use App\Services\TourismPriceData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TourismController extends Controller
{
    private const BENCHMARK_MIN_MARKET_ORGS = 2;

    public function index(TourismPriceData $priceData): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        if (! $organization->telegram_chat_id && ! $organization->telegram_connect_token) {
            $organization->update(['telegram_connect_token' => Str::random(32)]);
        }

        $servedDestinations = $organization->tourismDestinations()->get();

        $quoteResponses = $organization->quoteResponses()
            ->with(['quoteRequest', 'suggestions.claimedBy'])
            ->latest()
            ->get();

        return view('organizations.dashboard.tourism.index', [
            'organization' => $organization,
            'botUsername' => config('services.telegram.bot_username'),
            'destinations' => QuoteRequest::DESTINATIONS,
            'servedCountryCodes' => $servedDestinations->pluck('country_code')->all(),
            'servedDestinations' => $servedDestinations,
            'quoteResponses' => $quoteResponses,
            'benchmark' => $this->priceBenchmark($organization, $servedDestinations->pluck('country_code')->all(), $priceData),
        ]);
    }

    private function priceBenchmark(Organization $organization, array $countryCodes, TourismPriceData $priceData): Collection
    {
        if (empty($countryCodes)) {
            return collect();
        }

        $rows = $priceData->respondedSuggestionAmounts($countryCodes);

        return collect($countryCodes)
            ->map(function ($countryCode) use ($rows, $organization) {
                $forDestination = $rows->where('destination_country', $countryCode);
                $own = $forDestination->where('organization_id', $organization->id);

                if ($own->isEmpty()) {
                    return null;
                }

                $others = $forDestination->where('organization_id', '!=', $organization->id);
                $otherOrgCount = $others->pluck('organization_id')->unique()->count();

                return [
                    'country_code' => $countryCode,
                    'own_avg' => round($own->avg('amount_amd')),
                    'market_avg' => $otherOrgCount >= self::BENCHMARK_MIN_MARKET_ORGS ? round($others->avg('amount_amd')) : null,
                ];
            })
            ->filter()
            ->values();
    }

    public function refreshConnectLink(): RedirectResponse
    {
        Auth::guard('organization')->user()->organization->update([
            'telegram_chat_id' => null,
            'telegram_connect_token' => Str::random(32),
        ]);

        return redirect()->route('org.dashboard.tourism.index')->with('status', 'telegram-link-refreshed');
    }

    public function updateDestinations(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'destinations' => ['array'],
            'destinations.*' => ['string', Rule::in(QuoteRequest::DESTINATIONS)],
        ]);

        $organization = Auth::guard('organization')->user()->organization;
        $countryCodes = $validated['destinations'] ?? [];

        $previouslyServed = $organization->tourismDestinations()->pluck('country_code')->all();
        $newlyAdded = array_diff($countryCodes, $previouslyServed);

        $organization->tourismDestinations()->whereNotIn('country_code', $countryCodes)->delete();

        foreach ($countryCodes as $countryCode) {
            $organization->tourismDestinations()->firstOrCreate(['country_code' => $countryCode]);
        }

        foreach ($newlyAdded as $countryCode) {
            NotifyDestinationAlertsJob::dispatch($countryCode);
            BackfillOpenRequestsToNewPartnerJob::dispatch($organization->id, $countryCode);
        }

        return redirect()->route('org.dashboard.tourism.index')->with('status', 'destinations-saved');
    }

    public function updateDestinationPause(Request $request, string $locale, string $destination): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;
        $destination = $organization->tourismDestinations()->findOrFail($destination);

        $validated = $request->validate([
            'is_paused' => ['required', 'boolean'],
            'paused_until' => ['nullable', 'date', 'after:today'],
        ]);

        $destination->update([
            'is_paused' => $validated['is_paused'],
            'paused_until' => $validated['is_paused'] ? ($validated['paused_until'] ?? null) : null,
        ]);

        return redirect()->route('org.dashboard.tourism.index')->with('status', 'destination-pause-updated');
    }

    public function updateLeadPreferences(Request $request): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;

        $validated = $request->validate([
            'min_lead_budget_amd' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'min_lead_party_size' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $organization->update([
            'min_lead_budget_amd' => $validated['min_lead_budget_amd'] ?? null,
            'min_lead_party_size' => $validated['min_lead_party_size'] ?? null,
        ]);

        return redirect()->route('org.dashboard.tourism.index')->with('status', 'lead-preferences-updated');
    }
}
