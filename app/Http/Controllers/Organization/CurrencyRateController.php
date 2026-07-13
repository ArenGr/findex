<?php

namespace App\Http\Controllers\Organization;

use App\Enums\RateType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CurrencyRateController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        return view('organizations.dashboard.rates.index', [
            'rates' => $organization->currencyRates()->with('currency')->get(),
        ]);
    }

    public function create(): View
    {
        return view('organizations.dashboard.rates.create', [
            'currencies' => Currency::where('is_active', true)->orderBy('sort_order')->get(),
            'rateTypes' => RateType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;

        $validated = $this->validated($request);

        $rate = CurrencyRate::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'currency_id' => $validated['currency_id'],
                'rate_type' => $validated['rate_type'],
            ],
            [
                'buy_rate' => $validated['buy_rate'],
                'sell_rate' => $validated['sell_rate'],
                'source_url' => 'manual',
                'scraped_at' => now(),
            ]
        );

        $this->logHistoryIfChanged($rate);

        return redirect()->route('org.dashboard.rates.index')->with('status', 'rate-saved');
    }

    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment. Scoping the
     * lookup through the authenticated organization's own rates is also
     * what enforces that an org can only edit its own rates.
     */
    public function edit(string $locale, string $rate): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        return view('organizations.dashboard.rates.edit', [
            'rate' => $organization->currencyRates()->with('currency')->findOrFail($rate),
        ]);
    }

    public function update(Request $request, string $locale, string $rate): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;
        $rate = $organization->currencyRates()->findOrFail($rate);

        $validated = $request->validate([
            'buy_rate' => ['required', 'numeric', 'min:0'],
            'sell_rate' => ['required', 'numeric', 'min:0'],
        ]);

        $rate->update([
            ...$validated,
            'source_url' => 'manual',
            'scraped_at' => now(),
        ]);

        $this->logHistoryIfChanged($rate);

        return redirect()->route('org.dashboard.rates.index')->with('status', 'rate-saved');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'currency_id' => [
                'required',
                Rule::exists('currencies', 'id')->where('is_active', true),
            ],
            'rate_type' => ['required', Rule::enum(RateType::class)],
            'buy_rate' => ['required', 'numeric', 'min:0'],
            'sell_rate' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function logHistoryIfChanged(CurrencyRate $rate): void
    {
        if ($rate->wasRecentlyCreated || $rate->wasChanged(['buy_rate', 'sell_rate'])) {
            CurrencyRateHistory::createFromRate($rate);
        }
    }
}
