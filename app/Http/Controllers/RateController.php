<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RateController extends Controller
{
    public function index(Request $request): View
    {
        $currencies = Currency::where('is_active', true)->orderBy('sort_order')->get();
        $selectedCurrency = $currencies->firstWhere('code', $request->query('currency')) ?? $currencies->first();

        $selectedType = collect(RateType::cases())->first(
            fn (RateType $type) => $type->value === $request->query('type')
        ) ?? RateType::CASH;

        $sort = in_array($request->query('sort'), ['buy_rate', 'sell_rate', 'spread'], true)
            ? $request->query('sort')
            : 'sell_rate';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        // Only "bank" and "exchange" organizations ever carry currency rates,
        // but which of the two actually appear depends on real data - built
        // from what exists rather than a hard-coded list.
        $orgTypes = Organization::active()->whereHas('currencyRates')->distinct()->pluck('type');
        $selectedOrgType = $orgTypes->contains($request->query('org_type')) ? $request->query('org_type') : null;

        $organizations = Organization::active()
            ->whereHas('currencyRates')
            ->when($selectedOrgType, fn ($query) => $query->where('type', $selectedOrgType))
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'type']);
        $selectedOrganization = $request->filled('organization')
            ? $organizations->firstWhere('slug', $request->query('organization'))
            : null;

        // Rates aren't tied to a branch - a "city" filter really means "banks
        // with a branch in that city". Hidden entirely in the view when no
        // organization has entered any branches yet (see Branch model).
        $cities = Branch::active()->whereNotNull('city')->where('city', '!=', '')
            ->distinct()->orderBy('city')->pluck('city');
        $selectedCity = $request->filled('city') && $cities->contains($request->query('city'))
            ? $request->query('city')
            : null;

        $rates = CurrencyRate::query()
            ->with(['organization' => fn ($query) => $query->withRatingStats(), 'currency'])
            ->whereHas('organization', fn ($query) => $query->active())
            ->when($selectedCurrency, fn ($query) => $query->where('currency_id', $selectedCurrency->id))
            ->where('rate_type', $selectedType)
            ->when($selectedOrganization, fn ($query) => $query->where('organization_id', $selectedOrganization->id))
            ->when($selectedOrgType, fn ($query) => $query->whereHas(
                'organization',
                fn ($org) => $org->where('type', $selectedOrgType)
            ))
            ->when($selectedCity, fn ($query) => $query->whereHas(
                'organization.branches',
                fn ($branches) => $branches->active()->where('city', $selectedCity)
            ))
            ->when(
                $sort === 'spread',
                fn ($query) => $query->orderByRaw("(sell_rate - buy_rate) {$direction}"),
                fn ($query) => $query->orderBy($sort, $direction)
            )
            ->paginate(20)
            ->withQueryString();

        return view('rates.index', [
            'currencies' => $currencies,
            'selectedCurrency' => $selectedCurrency,
            'rateTypes' => RateType::cases(),
            'selectedType' => $selectedType,
            'orgTypes' => $orgTypes,
            'selectedOrgType' => $selectedOrgType,
            'organizations' => $organizations,
            'selectedOrganization' => $selectedOrganization,
            'cities' => $cities,
            'selectedCity' => $selectedCity,
            'sort' => $sort,
            'direction' => $direction,
            'rates' => $rates,
        ]);
    }
}
