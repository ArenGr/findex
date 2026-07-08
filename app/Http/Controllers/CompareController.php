<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\CurrencyRate;
use App\Models\MortgageOffer;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompareController extends Controller
{
    /**
     * Currencies shown in the comparison table, in display order. Kept to a
     * small curated set (rather than every currency) so the table stays
     * readable with 2-3 organizations side by side.
     */
    private const CURRENCIES = ['USD', 'EUR', 'RUB'];

    private const MAX_ORGANIZATIONS = 3;

    public function show(string $locale, Request $request): View
    {
        $slugs = array_slice(
            array_values(array_unique(array_filter(explode(',', (string) $request->query('orgs', ''))))),
            0,
            self::MAX_ORGANIZATIONS
        );

        $organizations = Organization::active()
            ->withRatingStats()
            ->whereIn('slug', $slugs)
            ->get()
            ->sortBy(fn ($organization) => array_search($organization->slug, $slugs))
            ->values();

        $organizationIds = $organizations->pluck('id');

        $ratesByOrgId = CurrencyRate::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('rate_type', RateType::CASH)
            ->whereHas('currency', fn ($query) => $query->whereIn('code', self::CURRENCIES))
            ->with('currency')
            ->get()
            ->groupBy('organization_id');

        $mortgagesByOrgId = MortgageOffer::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('category', 'secondary_market')
            ->get()
            ->groupBy('organization_id');

        return view('organizations.compare', [
            'organizations' => $organizations,
            'currencies' => self::CURRENCIES,
            'ratesByOrgId' => $ratesByOrgId,
            'mortgagesByOrgId' => $mortgagesByOrgId,
        ]);
    }
}
