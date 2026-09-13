<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\CurrencyRateHistory;
use App\Models\Organization;
use App\Services\Cache\OrgRatingsCache;
use App\Services\Cache\RateCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RateController extends Controller
{
    // Ten rows a page.
    private const PER_PAGE = 10;

    // Seeds the "you pay" column so it is on screen before anyone types.
    private const TTL_MINUTES = 360;

    public function index(Request $request): View
    {
        $currencies = collect(Cache::tags([RateCache::TAG])->remember(
            'rates.currencies.active',
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Currency::where('is_active', true)->orderBy('sort_order')->get()->toArray()
        ))->map(fn (array $row) => (object) $row);
        $selectedCurrency = $currencies->firstWhere('code', $request->query('currency')) ?? $currencies->first();

        $selectedType = collect(RateType::cases())->first(
            fn (RateType $type) => $type->value === $request->query('type')
        ) ?? RateType::CASH;

        $availableTypes = collect(Cache::tags([RateCache::TAG])->remember(
            'rates.types_for_currency.'.($selectedCurrency->id ?? 'none'),
            now()->addMinutes(self::TTL_MINUTES),
            fn () => CurrencyRate::query()
                ->when($selectedCurrency, fn ($query) => $query->where('currency_id', $selectedCurrency->id))
                ->whereHas('organization', fn ($query) => $query->active())
                ->distinct()
                ->pluck('rate_type')
                ->map(fn ($type) => $type instanceof RateType ? $type->value : (string) $type)
                ->values()
                ->all()
        ));

        // DISTINCT returns them in whatever order the index yields ("card, cash, central_bank, cross...").
        $availableTypes = collect(RateType::cases())
            ->map(fn (RateType $type) => $type->value)
            ->filter(fn (string $type) => $availableTypes->contains($type))
            ->reject(fn (string $type) => $type === RateType::CENTRAL_BANK->value)
            ->values();

        $intent = $request->query('intent') === 'buy' ? 'buy' : 'sell';

        // Null unless the visitor asked for a calculation.
        $amount = $this->amountFromQuery($request);

        [$latitude, $longitude] = $this->coordinatesFromQuery($request);
        $hasLocation = $latitude !== null && $longitude !== null;

        // Sorting is done from the column headings, so the sort keys are named after columns.
        $sortOptions = ['best', 'buy', 'sell', 'spread', 'updated'];

        if ($hasLocation) {
            $sortOptions[] = 'distance';
        }

        $sortKey = in_array($request->query('sort'), $sortOptions, true)
            ? $request->query('sort')
            : ($hasLocation ? 'distance' : 'best');

        $naturalDirection = match ($sortKey) {
            'buy' => 'desc',
            'sell', 'spread', 'distance' => 'asc',
            'updated' => 'desc',
            // Selling the currency, the highest buy rate wins; buying it, the lowest sell rate does.
            default => $intent === 'sell' ? 'desc' : 'asc',
        };

        $requestedDirection = strtolower((string) $request->query('dir'));
        $direction = in_array($requestedDirection, ['asc', 'desc'], true)
            ? $requestedDirection
            : $naturalDirection;

        // Resolved to a column for the query layer, which is unchanged and still speaks in columns.
        $sort = match ($sortKey) {
            'buy' => 'buy_rate',
            'sell' => 'sell_rate',
            'spread' => 'spread',
            'updated' => 'scraped_at',
            'distance' => 'distance',
            default => $intent === 'sell' ? 'buy_rate' : 'sell_rate',
        };

        $search = trim((string) $request->query('q'));
        $search = mb_substr($search, 0, 60);

        $orgTypes = collect(Cache::tags([RateCache::TAG])->remember(
            'rates.org_types',
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Organization::active()->whereHas('currencyRates')->distinct()->pluck('type')->all()
        ));
        $selectedOrgType = $orgTypes->contains($request->query('org_type')) ? $request->query('org_type') : null;

        $organizations = $this->organizations($selectedOrgType);

        $alertOrganizations = $selectedOrgType === null ? $organizations : $this->organizations(null);

        $selectedOrganization = $request->filled('organization')
            ? $organizations->firstWhere('slug', $request->query('organization'))
            : null;

        // Rates aren't tied to a branch - a "city" filter really means "banks with a branch in that city".
        $cities = collect(Cache::tags([RateCache::TAG])->remember(
            'rates.cities',
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Branch::active()->whereNotNull('city')->where('city', '!=', '')
                ->distinct()->orderBy('city')->pluck('city')->all()
        ));
        $selectedCity = $request->filled('city') && $cities->contains($request->query('city'))
            ? $request->query('city')
            : null;

        // "Open now".
        $openNow = $request->boolean('open');
        $openOrganizationIds = $openNow ? $this->organizationsOpenNow() : null;

        // List or map.
        $viewMode = $request->query('view') === 'map' ? 'map' : 'list';

        $page = (int) $request->query('page', 1);

        $filters = compact('selectedCurrency', 'selectedType', 'selectedOrganization', 'selectedOrgType', 'selectedCity', 'sort', 'direction', 'page', 'openOrganizationIds');

        $cached = $hasLocation
            ? $this->fetchNearbyRates($filters, $latitude, $longitude)
            : $this->fetchCachedRates($filters);

        // When each rate last actually moved, as opposed to when it was last looked at.
        $cached['items'] = $this->withLastChanged($cached['items']);

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $cached['items'] = array_values(array_filter(
                $cached['items'],
                fn (array $row) => str_contains(mb_strtolower((string) $row['organization_name']), $needle),
            ));
            $cached['total'] = count($cached['items']);
        }

        $ranked = $this->rankRows(
            collect($cached['items'])->map(fn (array $row) => (object) $row)->all(),
            $intent,
        );

        $rates = new LengthAwarePaginator(
            collect($ranked['rows'])->forPage($page, self::PER_PAGE)->values(),
            $ranked['count'],
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('rates.index', [
            'currencies' => $currencies,
            'selectedCurrency' => $selectedCurrency,
            'rateTypes' => RateType::cases(),
            'selectedType' => $selectedType,
            'availableTypes' => $availableTypes,
            'suggestedType' => $this->suggestedType($selectedType, $availableTypes),
            'orgTypes' => $orgTypes,
            'selectedOrgType' => $selectedOrgType,
            'organizations' => $organizations,
            'alertOrganizations' => $alertOrganizations,
            'alertRateTypes' => RateType::cases(),
            'selectedOrganization' => $selectedOrganization,
            'cities' => $cities,
            'selectedCity' => $selectedCity,
            'viewMode' => $viewMode,
            // Every geocoded branch of every organization on the page.
            'mapBranches' => $viewMode === 'map' ? $this->mapBranches($cached['items']) : [],
            'quoteCurrencies' => $currencies->filter(
                fn ($row) => isset(config('exchange-quotes.minimum_amounts')[$row->code])
            )->values(),
            'openNow' => $openNow,
            'sort' => $sortKey,
            'sortOptions' => $sortOptions,
            'direction' => $direction,
            'search' => $search,
            'intent' => $intent,
            'amount' => $amount,
            'rates' => $rates,
            // Every matching row, ranked - what the page says about the market is said about all of it.
            'ranked' => $ranked,
            // The ten of them this page shows.
            'pageRows' => $rates->items(),
            'quoteMinimum' => config('exchange-quotes.minimum_amounts')[$selectedCurrency?->code] ?? null,
            'centralBankRate' => $this->centralBankRate($selectedCurrency),
            'hasLocation' => $hasLocation,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    public static function rateFieldForIntent(string $intent): string
    {
        return $intent === 'sell' ? 'buy_rate' : 'sell_rate';
    }

    // The official reference rate for this currency, or null when we have not scraped one.
    private function centralBankRate(?object $selectedCurrency): ?array
    {
        if (! $selectedCurrency) {
            return null;
        }

        return Cache::tags([RateCache::TAG])->remember(
            'rates.central_bank.'.$selectedCurrency->id,
            now()->addMinutes(self::TTL_MINUTES),
            function () use ($selectedCurrency) {
                $rate = CurrencyRate::query()
                    ->where('currency_id', $selectedCurrency->id)
                    ->where('rate_type', RateType::CENTRAL_BANK)
                    ->whereHas('organization', fn ($query) => $query->active())
                    ->latest('scraped_at')
                    ->first();

                return $rate ? [
                    'rate' => (string) $rate->sell_rate,
                    'scraped_at' => $rate->scraped_at?->toIso8601String(),
                ] : null;
            }
        );
    }

    /** Stable array-key form of a rate, immune to float-to-int key casting. */
    private static function rateKey(float $value): string
    {
        return number_format($value, 4, '.', '');
    }

    /**
     * Rank every visible row as one list and resolve the winner.
     *
     * @param  array<int, object>  $rows
     * @return array<string, mixed>
     */
    /**
     * @return Collection<int, object>
     */
    private function organizations(?string $orgType): Collection
    {
        return collect(Cache::tags([RateCache::TAG])->remember(
            'rates.organizations.'.($orgType ?? 'all'),
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Organization::active()
                ->whereHas('currencyRates')
                ->when($orgType, fn ($query) => $query->where('type', $orgType))
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'type'])
                ->toArray()
        ))->map(fn (array $row) => (object) $row);
    }

    private function rankRows(array $rows, string $intent): array
    {
        $values = collect($rows)
            ->pluck(self::rateFieldForIntent($intent))
            ->map(fn ($value) => (float) $value);

        if ($values->isEmpty()) {
            return ['rows' => [], 'count' => 0, 'best_value' => null, 'worst_value' => null, 'spread' => null];
        }

        // Selling: the most AMD back wins. Buying: the least paid wins.
        $best = $intent === 'sell' ? $values->max() : $values->min();
        $worst = $intent === 'sell' ? $values->min() : $values->max();

        $ordered = $values->unique()->sort()->values();
        if ($intent === 'sell') {
            $ordered = $ordered->reverse()->values();
        }

        $rankByValue = [];
        foreach ($ordered as $index => $value) {
            $rankByValue[self::rateKey($value)] = $index + 1;
        }

        $field = self::rateFieldForIntent($intent);
        foreach ($rows as $row) {
            $value = (float) $row->{$field};
            $row->rank = $rankByValue[self::rateKey($value)] ?? null;
        }

        return [
            'rows' => $rows,
            'count' => count($rows),
            'best_value' => $best,
            'worst_value' => $worst,
            // Only meaningful with more than one quote to compare.
            'spread' => count($rows) > 1 ? abs($best - $worst) : null,
        ];
    }

    private function suggestedType(RateType $selectedType, Collection $availableTypes): ?string
    {
        return $availableTypes->first(fn (string $type) => $type !== $selectedType->value);
    }

    private function amountFromQuery(Request $request): ?float
    {
        $amount = $request->query('amount');

        if (! is_numeric($amount)) {
            return null;
        }

        $amount = (float) $amount;

        return $amount > 0 && $amount <= 99999999.99 ? $amount : null;
    }

    /**
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
     * @return array{total: int, items: array}
     */
    private function fetchCachedRates(array $filters): array
    {
        ['selectedCurrency' => $selectedCurrency, 'selectedType' => $selectedType, 'selectedOrganization' => $selectedOrganization,
            'selectedOrgType' => $selectedOrgType, 'selectedCity' => $selectedCity, 'sort' => $sort, 'direction' => $direction,
            'openOrganizationIds' => $openOrganizationIds] = $filters;

        $cacheKey = 'rates.listing.'.md5(json_encode([
            'v3', app()->getLocale(), $selectedCurrency?->id, $selectedType->value, $selectedOrganization?->id,
            $selectedOrgType, $selectedCity, $sort, $direction, $openOrganizationIds,
        ]));

        return Cache::tags([RateCache::TAG, OrgRatingsCache::TAG])->remember(
            $cacheKey,
            now()->addMinutes(30),
            function () use ($selectedCurrency, $selectedType, $selectedOrganization, $selectedOrgType, $selectedCity, $sort, $direction, $openOrganizationIds) {
                // Every matching row, not one page of them.
                $rows = $this->baseQuery($selectedCurrency, $selectedType, $selectedOrganization, $selectedOrgType, $selectedCity, $openOrganizationIds)
                    ->when(
                        $sort === 'spread',
                        fn ($query) => $query->orderByRaw("(sell_rate - buy_rate) {$direction}"),
                        fn ($query) => $query->orderBy($sort, $direction)
                    )
                    ->get();

                return [
                    'total' => $rows->count(),
                    'items' => $rows
                        ->map(fn (CurrencyRate $rate) => $this->rateRow($rate, $this->directionsBranch($rate, $selectedCity)))
                        ->all(),
                ];
            }
        );
    }

    /**
     * @return array{total: int, items: array}
     */
    private function fetchNearbyRates(array $filters, float $latitude, float $longitude): array
    {
        ['selectedCurrency' => $selectedCurrency, 'selectedType' => $selectedType, 'selectedOrganization' => $selectedOrganization,
            'selectedOrgType' => $selectedOrgType, 'selectedCity' => $selectedCity, 'sort' => $sort, 'direction' => $direction,
            'openOrganizationIds' => $openOrganizationIds] = $filters;

        $rows = $this->baseQuery($selectedCurrency, $selectedType, $selectedOrganization, $selectedOrgType, $selectedCity, $openOrganizationIds)
            ->get()
            ->map(function (CurrencyRate $rate) use ($latitude, $longitude, $selectedCity) {
                $nearest = $rate->organization->branches
                    ->filter(fn (Branch $branch) => $branch->is_active)
                    ->map(fn (Branch $branch) => [$branch, $branch->distanceInKmFrom($latitude, $longitude)])
                    ->reject(fn (array $pair) => $pair[1] === null)
                    ->sortBy(fn (array $pair) => $pair[1])
                    ->first();

                return [
                    ...$this->rateRow($rate, $this->directionsBranch($rate, $selectedCity, $nearest[0] ?? null)),
                    'distance_km' => $nearest[1] ?? null,
                ];
            });

        $sorted = $sort === 'distance'
            ? $rows->sortBy(fn (array $row) => $row['distance_km'] ?? INF, descending: $direction === 'desc')
            : ($sort === 'spread'
                ? $rows->sortBy('spread', descending: $direction === 'desc')
                : $rows->sortBy($sort, descending: $direction === 'desc'));

        return [
            'total' => $sorted->count(),
            'items' => $sorted->values()->all(),
        ];
    }

    private function baseQuery($selectedCurrency, RateType $selectedType, $selectedOrganization, ?string $selectedOrgType, ?string $selectedCity, ?array $openOrganizationIds = null): Builder
    {
        return CurrencyRate::query()
            ->when($openOrganizationIds !== null, fn ($query) => $query->whereIn('organization_id', $openOrganizationIds))
            ->with(['organization' => fn ($query) => $query->withRatingStats()->with('branches'), 'currency'])
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

    private function directionsBranch(CurrencyRate $rate, ?string $city, ?Branch $nearest = null): ?array
    {
        $branch = $nearest;

        if ($branch === null) {
            $candidates = $rate->organization->branches
                ->filter(fn (Branch $b) => $b->is_active && $b->latitude !== null && $b->longitude !== null)
                ->when($city !== null, fn ($rows) => $rows->where('city', $city));

            $branch = $candidates->count() === 1 ? $candidates->first() : null;
        }

        if ($branch === null || $branch->latitude === null || $branch->longitude === null) {
            return null;
        }

        return [
            'name' => $branch->name,
            'address' => $branch->address,
            'url' => 'https://www.google.com/maps/dir/?api=1&destination='.$branch->latitude.','.$branch->longitude,
        ];
    }

    /**
     * Stamps each row with when its rate last changed, in one query for the whole page.
     *
     * @param  array<int, array>  $items
     * @return array<int, array>
     */
    private function withLastChanged(array $items): array
    {
        $ids = collect($items)->pluck('id')->filter()->all();

        if ($ids === []) {
            return $items;
        }

        $changed = CurrencyRateHistory::query()
            ->whereIn('currency_rate_id', $ids)
            ->groupBy('currency_rate_id')
            ->selectRaw('currency_rate_id, MAX(scraped_at) as last_changed')
            ->pluck('last_changed', 'currency_rate_id');

        return collect($items)
            ->map(fn (array $row) => [...$row, 'changed_at' => $changed[$row['id']] ?? null])
            ->all();
    }

    /**
     * Organizations with at least one branch open right now.
     *
     * @return array<int, int>
     */
    private function organizationsOpenNow(): array
    {
        return Branch::query()
            ->where('is_active', true)
            ->whereNotNull('opening_hours')
            ->get()
            ->filter(fn (Branch $branch) => $branch->isOpenAt() === true)
            ->pluck('organization_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The branches behind the rows on this page, grouped by organization.
     *
     * @param  array<int, array>  $items
     * @return array<int, array<int, array>>
     */
    private function mapBranches(array $items): array
    {
        $organizationIds = collect($items)->pluck('organization_id')->unique()->all();

        if ($organizationIds === []) {
            return [];
        }

        return Branch::query()
            ->whereIn('organization_id', $organizationIds)
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->groupBy('organization_id')
            ->map(fn ($branches) => $branches->map(function (Branch $branch) {
                $hours = $branch->hoursOn(now());

                return [
                    'name' => $branch->name,
                    'address' => $branch->address,
                    'city' => $branch->city,
                    'lat' => (float) $branch->latitude,
                    'lng' => (float) $branch->longitude,
                    // Three states, not two: open, shut, and no hours on file.
                    'open' => $branch->isOpenAt(),
                    'hours' => $hours ? $hours[0].' - '.$hours[1] : null,
                ];
            })->values()->all())
            ->all();
    }

    private function rateRow(CurrencyRate $rate, ?array $branch = null): array
    {
        return [
            'id' => $rate->id,
            'branch' => $branch,
            'buy_rate' => (string) $rate->buy_rate,
            'sell_rate' => (string) $rate->sell_rate,
            'spread' => $rate->getSpread(),
            'scraped_at' => $rate->scraped_at?->toIso8601String(),
            'organization_id' => $rate->organization_id,
            'organization_type' => $rate->organization->type,
            'organization_name' => $rate->organization->name,
            'organization_logo' => $rate->organization->logo,
            'organization_url' => route('organizations.show', $rate->organization),
            'organization_reviews_count' => $rate->organization->reviews_count,
            'organization_reviews_avg_rating' => $rate->organization->reviews_avg_rating,
        ];
    }
}
