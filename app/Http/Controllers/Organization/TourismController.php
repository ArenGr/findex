<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TourismController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user();

        // A connect link is only useful before the partner has linked their
        // chat - generate one lazily so the dashboard always has a live link
        // to show, without a separate "generate" step for the common case.
        if (!$organization->telegram_chat_id && !$organization->telegram_connect_token) {
            $organization->update(['telegram_connect_token' => Str::random(32)]);
        }

        $servedCountryCodes = $organization->tourismDestinations()->pluck('country_code')->all();

        $quoteResponses = $organization->quoteResponses()
            ->with('quoteRequest')
            ->latest()
            ->get();

        return view('organizations.dashboard.tourism.index', [
            'organization' => $organization,
            'botUsername' => config('services.telegram.bot_username'),
            'destinations' => QuoteRequest::DESTINATIONS,
            'servedCountryCodes' => $servedCountryCodes,
            'quoteResponses' => $quoteResponses,
        ]);
    }

    public function refreshConnectLink(): RedirectResponse
    {
        Auth::guard('organization')->user()->update([
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

        $organization = Auth::guard('organization')->user();
        $countryCodes = $validated['destinations'] ?? [];

        $organization->tourismDestinations()->whereNotIn('country_code', $countryCodes)->delete();

        foreach ($countryCodes as $countryCode) {
            $organization->tourismDestinations()->firstOrCreate(['country_code' => $countryCode]);
        }

        return redirect()->route('org.dashboard.tourism.index')->with('status', 'destinations-saved');
    }
}
