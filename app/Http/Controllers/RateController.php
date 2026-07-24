<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Services\Cache\OrgRatingsCache;
use App\Services\Cache\RateCache;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RateController extends Controller
{
    // Backstop only - RateCache::invalidate() (see the CurrencyRate/
    // MortgageOffer/Currency/Organization/Branch booted() hooks) is the
    // real invalidation path; this just bounds the worst case if a write
    // path is ever missed.
    private const TTL_MINUTES = 360;

    public function index(Request $request): View
    {
        // These four are the same for every visitor regardless of which
        // filters they pick - cached once per 'rates' tag generation rather
        // than re-queried on every single /rates hit. Cache values must be
        // plain arrays, not Eloquent Collections/Models: config/cache.php's
        // 'serializable_classes' => false means Redis will only unserialize
        // arrays/scalars, not objects (a deliberate anti-object-injection
        // hardening) - object rows are rehydrated as stdClass immediately
        // after the cache read instead, cheap since it's a handful of rows.
        $currencies = collect(Cache::tags([RateCache::TAG])->remember(
            'rates.currencies.active',
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Currency::where('is_active', true)->orderBy('sort_order')->get()->toArray()
        ))->map(fn (array $row) => (object) $row);
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
        $orgTypes = collect(Cache::tags([RateCache::TAG])->remember(
            'rates.org_types',
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Organization::active()->whereHas('currencyRates')->distinct()->pluck('type')->all()
        ));
        $selectedOrgType = $orgTypes->contains($request->query('org_type')) ? $request->query('org_type') : null;

        $organizations = collect(Cache::tags([RateCache::TAG])->remember(
            'rates.organizations.'.($selectedOrgType ?? 'all'),
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Organization::active()
                ->whereHas('currencyRates')
                ->when($selectedOrgType, fn ($query) => $query->where('type', $selectedOrgType))
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'type'])
                ->toArray()
        ))->map(fn (array $row) => (object) $row);
        $selectedOrganization = $request->filled('organization')
            ? $organizations->firstWhere('slug', $request->query('organization'))
            : null;

        // Rates aren't tied to a branch - a "city" filter really means "banks
        // with a branch in that city". Hidden entirely in the view when no
        // organization has entered any branches yet (see Branch model).
        $cities = collect(Cache::tags([RateCache::TAG])->remember(
            'rates.cities',
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Branch::active()->whereNotNull('city')->where('city', '!=', '')
                ->distinct()->orderBy('city')->pluck('city')->all()
        ));
        $selectedCity = $request->filled('city') && $cities->contains($request->query('city'))
            ? $request->query('city')
            : null;

        $page = (int) $request->query('page', 1);

        // Depends on both rate data and Organization::withRatingStats()
        // (review counts/averages), so it needs both tags - a review write
        // invalidates this without touching the simpler rate-only caches
        // above, and vice versa. Keyed on every filter input plus the page
        // and locale (row URLs like organizations.show are locale-prefixed,
        // so a locale-less key would leak one locale's links into another's
        // cached render - see rates-table.blade.php for the same caveat).
        $cacheKey = 'rates.listing.'.md5(json_encode([
            app()->getLocale(), $selectedCurrency?->id, $selectedType->value, $selectedOrganization?->id,
            $selectedOrgType, $selectedCity, $sort, $direction, $page,
        ]));

        $cached = Cache::tags([RateCache::TAG, OrgRatingsCache::TAG])->remember(
            $cacheKey,
            now()->addMinutes(30),
            function () use ($selectedCurrency, $selectedType, $selectedOrganization, $selectedOrgType, $selectedCity, $sort, $direction, $page) {
                $paginator = CurrencyRate::query()
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
                    ->paginate(20, page: $page);

                return [
                    'total' => $paginator->total(),
                    'items' => $paginator->getCollection()->map(fn (CurrencyRate $rate) => [
                        'id' => $rate->id,
                        'buy_rate' => (string) $rate->buy_rate,
                        'sell_rate' => (string) $rate->sell_rate,
                        'spread' => $rate->getSpread(),
                        'scraped_at' => $rate->scraped_at?->toIso8601String(),
                        'organization_id' => $rate->organization_id,
                        'organization_name' => $rate->organization->name,
                        'organization_logo' => $rate->organization->logo,
                        'organization_url' => route('organizations.show', $rate->organization),
                        'organization_reviews_count' => $rate->organization->reviews_count,
                        'organization_reviews_avg_rating' => $rate->organization->reviews_avg_rating,
                    ])->all(),
                ];
            }
        );

        // Rebuilt fresh every request (cheap - it's just wrapping the small
        // cached array), not itself cached: LengthAwarePaginator is an
        // object, and config/cache.php's 'serializable_classes' => false
        // means Redis can only round-trip the plain array above.
        $rates = new LengthAwarePaginator(
            collect($cached['items'])->map(fn (array $row) => (object) $row),
            $cached['total'],
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

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
