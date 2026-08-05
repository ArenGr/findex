<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Organization;
use App\Services\Cache\OrgRatingsCache;
use App\Services\Cache\RateCache;
use Illuminate\Database\Eloquent\Builder;
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

        // "distance" only means anything once we actually have the
        // visitor's coordinates - falls back to the normal default rather
        // than erroring if the sort param is stale (e.g. a bookmarked link
        // from when location sharing was on, tried again after browser
        // permission was revoked).
        [$latitude, $longitude] = $this->coordinatesFromQuery($request);
        $hasLocation = $latitude !== null && $longitude !== null;

        $sortOptions = $hasLocation ? ['buy_rate', 'sell_rate', 'spread', 'distance'] : ['buy_rate', 'sell_rate', 'spread'];
        $sort = in_array($request->query('sort'), $sortOptions, true)
            ? $request->query('sort')
            : ($hasLocation ? 'distance' : 'sell_rate');
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

        $filters = compact('selectedCurrency', 'selectedType', 'selectedOrganization', 'selectedOrgType', 'selectedCity', 'sort', 'direction', 'page');

        // Distance-sorted results are specific to wherever this one visitor
        // happens to be standing - sharing them under the filter-only cache
        // key below would leak one visitor's ranking to every other visitor
        // hitting the same currency/type/city/sort combination. Computed
        // fresh every time instead, on the same filtered query.
        $cached = $hasLocation
            ? $this->fetchNearbyRates($filters, $latitude, $longitude)
            : $this->fetchCachedRates($filters);

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
            'hasLocation' => $hasLocation,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * Rounded to ~11m precision (5 decimal places) - plenty for "which
     * branch is closest", and short/stable enough to sit in a bookmarkable
     * URL without looking like raw sensor noise. Silently dropped (both or
     * neither) rather than rejected outright if out of range or malformed -
     * a bad/tampered coordinate just means the page falls back to its
     * normal non-location behavior instead of erroring.
     *
     * @return array{0: ?float, 1: ?float}
     */
    private function coordinatesFromQuery(Request $request): array
    {
        $latitude = $request->query('lat');
        $longitude = $request->query('lng');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return [null, null];
        }

        $latitude = round((float) $latitude, 5);
        $longitude = round((float) $longitude, 5);

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return [null, null];
        }

        return [$latitude, $longitude];
    }

    /**
     * The normal, shared-across-every-visitor path - identical to how this
     * controller worked before "find nearby" existed.
     *
     * @return array{total: int, items: array}
     */
    private function fetchCachedRates(array $filters): array
    {
        ['selectedCurrency' => $selectedCurrency, 'selectedType' => $selectedType, 'selectedOrganization' => $selectedOrganization,
            'selectedOrgType' => $selectedOrgType, 'selectedCity' => $selectedCity, 'sort' => $sort, 'direction' => $direction, 'page' => $page] = $filters;

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

        return Cache::tags([RateCache::TAG, OrgRatingsCache::TAG])->remember(
            $cacheKey,
            now()->addMinutes(30),
            function () use ($selectedCurrency, $selectedType, $selectedOrganization, $selectedOrgType, $selectedCity, $sort, $direction, $page) {
                $paginator = $this->baseQuery($selectedCurrency, $selectedType, $selectedOrganization, $selectedOrgType, $selectedCity)
                    ->when(
                        $sort === 'spread',
                        fn ($query) => $query->orderByRaw("(sell_rate - buy_rate) {$direction}"),
                        fn ($query) => $query->orderBy($sort, $direction)
                    )
                    ->paginate(20, page: $page);

                return [
                    'total' => $paginator->total(),
                    'items' => $paginator->getCollection()->map(fn (CurrencyRate $rate) => $this->rateRow($rate))->all(),
                ];
            }
        );
    }

    /**
     * Distance can't be computed in the database portably (MySQL in
     * production, SQLite in tests use different trig-function syntax), so
     * this loads every row matching the current filters, computes each
     * one's distance to the visitor in PHP (Branch::distanceInKmFrom(),
     * the same haversine formula either way), sorts/paginates on that, and
     * skips the shared cache entirely - see index()'s comment on why a
     * location-specific result can't be cached under the shared key.
     * Acceptable at this app's data volume; would need a real spatial
     * index or precomputed geohash bucketing to stay fast at a much larger
     * scale.
     *
     * @return array{total: int, items: array}
     */
    private function fetchNearbyRates(array $filters, float $latitude, float $longitude): array
    {
        ['selectedCurrency' => $selectedCurrency, 'selectedType' => $selectedType, 'selectedOrganization' => $selectedOrganization,
            'selectedOrgType' => $selectedOrgType, 'selectedCity' => $selectedCity, 'sort' => $sort, 'direction' => $direction, 'page' => $page] = $filters;

        $rows = $this->baseQuery($selectedCurrency, $selectedType, $selectedOrganization, $selectedOrgType, $selectedCity)
            ->with('organization.branches')
            ->get()
            ->map(function (CurrencyRate $rate) use ($latitude, $longitude) {
                $distanceKm = $rate->organization->branches
                    ->filter(fn (Branch $branch) => $branch->is_active)
                    ->map(fn (Branch $branch) => $branch->distanceInKmFrom($latitude, $longitude))
                    ->filter(fn (?float $km) => $km !== null)
                    ->min();

                return [...$this->rateRow($rate), 'distance_km' => $distanceKm];
            });

        $sorted = $sort === 'distance'
            ? $rows->sortBy(fn (array $row) => $row['distance_km'] ?? INF, descending: $direction === 'desc')
            : ($sort === 'spread'
                ? $rows->sortBy('spread', descending: $direction === 'desc')
                : $rows->sortBy($sort, descending: $direction === 'desc'));

        return [
            'total' => $sorted->count(),
            'items' => $sorted->values()->slice(($page - 1) * 20, 20)->all(),
        ];
    }

    private function baseQuery($selectedCurrency, RateType $selectedType, $selectedOrganization, ?string $selectedOrgType, ?string $selectedCity): Builder
    {
        return CurrencyRate::query()
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
            ));
    }

    private function rateRow(CurrencyRate $rate): array
    {
        return [
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
        ];
    }
}
