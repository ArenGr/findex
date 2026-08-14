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
    /**
     * Ten rows a page. Fourteen was the most any currency and type ever
     * showed, so a single list was never long - but it was long enough that
     * the rows at the bottom were read after scrolling past the fold, and ten
     * fits a screen.
     */
    private const PER_PAGE = 10;

    // Backstop only - RateCache::invalidate() (see the CurrencyRate/
    // MortgageOffer/Currency/Organization/Branch booted() hooks) is the
    // real invalidation path; this just bounds the worst case if a write
    // path is ever missed.
    /**
     * Seeds the "you pay" column so it is on screen before anyone types. Small
     * enough to read as an example rather than as a claim about the visitor.
     */
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

        // Coverage is very uneven per currency - KZT only has cash rates, and
        // card/cross/transfer come from one or two organizations - so offering
        // every rate type for every currency guarantees dead ends. Only the
        // types that actually have rows for this currency are offered, the
        // same rule the home page widget already applies (see
        // HomeRatesTableData). Cached as plain strings: rate_type is an enum
        // cast, and config/cache.php forbids objects in the cache.
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

        // DISTINCT returns them in whatever order the index yields
        // ("card, cash, central_bank, cross..."). Re-ordered to the enum's own
        // declaration order, which is already the order that makes sense to a
        // visitor: the two everyday types first, the central bank reference
        // rate - which isn't a place you can exchange at - last.
        // central_bank is dropped from the choices entirely: it is the
        // official reference rate, not somewhere a visitor can exchange money,
        // so offering it as a peer of Cash and Card sent people to rows they
        // could not act on. It is surfaced as a single reference line instead
        // (see $centralBankRate below).
        $availableTypes = collect(RateType::cases())
            ->map(fn (RateType $type) => $type->value)
            ->filter(fn (string $type) => $availableTypes->contains($type))
            ->reject(fn (string $type) => $type === RateType::CENTRAL_BANK->value)
            ->values();

        // What the visitor is trying to do, which is what decides the ranking:
        // buying the currency means the cheapest sell_rate wins, selling it
        // means the highest buy_rate does. Replaces asking them to reason
        // about which of two institution-side columns to sort by.
        //
        // Defaults to 'sell', which the page states as "I have USD, I want
        // AMD": the currency picked in the strip above is the one the visitor
        // has, so the two controls agree rather than contradicting each other.
        $intent = $request->query('intent') === 'buy' ? 'buy' : 'sell';

        // Null unless the visitor asked for a calculation. Most people arrive
        // to read today's rates, and a rate table is the honest answer to that;
        // totals are a second question, asked by typing an amount.
        // Display-layer only - deliberately not part of any cache key
        // (see fetchCachedRates).
        $amount = $this->amountFromQuery($request);

        // "distance" only means anything once we actually have the
        // visitor's coordinates - falls back to the normal default rather
        // than erroring if the sort param is stale (e.g. a bookmarked link
        // from when location sharing was on, tried again after browser
        // permission was revoked).
        [$latitude, $longitude] = $this->coordinatesFromQuery($request);
        $hasLocation = $latitude !== null && $longitude !== null;

        // Sorting is done from the column headings, so the sort keys are named
        // after columns. 'best' is not one of them: it is the ordering the page
        // arrives in, which already knows which of the two rate columns answers
        // the visitor's intent and which way it runs.
        //
        // 'distance' has no column of its own - it is set by "find nearby" and
        // shown under each name - but it sorts the same table, so it lives in
        // the same list.
        $sortOptions = ['best', 'buy', 'sell', 'spread', 'updated'];

        if ($hasLocation) {
            $sortOptions[] = 'distance';
        }

        $sortKey = in_array($request->query('sort'), $sortOptions, true)
            ? $request->query('sort')
            // Sharing a location is itself a statement of intent, so it picks
            // the sort; otherwise the direction does.
            : ($hasLocation ? 'distance' : 'best');

        // Every column has one direction that answers the question people are
        // actually asking of it - the highest buy rate, the lowest sell rate,
        // the tightest spread, the newest update - and that is what a first
        // press gives. Pressing the same heading again reverses it.
        $naturalDirection = match ($sortKey) {
            'buy' => 'desc',
            'sell', 'spread', 'distance' => 'asc',
            'updated' => 'desc',
            // Selling the currency, the highest buy rate wins; buying it, the
            // lowest sell rate does.
            default => $intent === 'sell' ? 'desc' : 'asc',
        };

        $requestedDirection = strtolower((string) $request->query('dir'));
        $direction = in_array($requestedDirection, ['asc', 'desc'], true)
            ? $requestedDirection
            : $naturalDirection;

        // Resolved to a column for the query layer, which is unchanged and
        // still speaks in columns.
        $sort = match ($sortKey) {
            'buy' => 'buy_rate',
            'sell' => 'sell_rate',
            'spread' => 'spread',
            'updated' => 'scraped_at',
            'distance' => 'distance',
            default => $intent === 'sell' ? 'buy_rate' : 'sell_rate',
        };

        // Fourteen rows is the most this page ever shows, so a name search is a
        // filter over what is already on screen rather than a query. Kept out
        // of the cache key below for the same reason it is cheap: baking a free
        // text field into a key fragments the cache once per phrase typed.
        $search = trim((string) $request->query('q'));
        $search = mb_substr($search, 0, 60);

        // Only "bank" and "exchange" organizations ever carry currency rates,
        // but which of the two actually appear depends on real data - built
        // from what exists rather than a hard-coded list.
        $orgTypes = collect(Cache::tags([RateCache::TAG])->remember(
            'rates.org_types',
            now()->addMinutes(self::TTL_MINUTES),
            fn () => Organization::active()->whereHas('currencyRates')->distinct()->pluck('type')->all()
        ));
        $selectedOrgType = $orgTypes->contains($request->query('org_type')) ? $request->query('org_type') : null;

        $organizations = $this->organizations($selectedOrgType);

        // The rate-alert modal is not scoped by the page's market filter - an
        // alert can name any organization. Reuses the same cache entry the
        // unfiltered page builds, so this costs a query only when a market
        // filter is active.
        $alertOrganizations = $selectedOrgType === null ? $organizations : $this->organizations(null);

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

        // "Open now". Evaluated in PHP over the branch table rather than in
        // SQL: the hours are a JSON column, the dataset is a couple of dozen
        // rows, and a JSON predicate would be written twice - once for MySQL in
        // production and once for SQLite in the test suite.
        $openNow = $request->boolean('open');
        $openOrganizationIds = $openNow ? $this->organizationsOpenNow() : null;

        // List or map. The list is the default and stays the default: a map is
        // slower to read a table of numbers off, and most visits are exactly
        // that. Only when it is asked for do we load the library at all.
        $viewMode = $request->query('view') === 'map' ? 'map' : 'list';

        $page = (int) $request->query('page', 1);

        $filters = compact('selectedCurrency', 'selectedType', 'selectedOrganization', 'selectedOrgType', 'selectedCity', 'sort', 'direction', 'page', 'openOrganizationIds');

        // Distance-sorted results are specific to wherever this one visitor
        // happens to be standing - sharing them under the filter-only cache
        // key below would leak one visitor's ranking to every other visitor
        // hitting the same currency/type/city/sort combination. Computed
        // fresh every time instead, on the same filtered query.
        $cached = $hasLocation
            ? $this->fetchNearbyRates($filters, $latitude, $longitude)
            : $this->fetchCachedRates($filters);

        // When each rate last actually moved, as opposed to when it was last
        // looked at. RateScraper only appends history when buy or sell changed,
        // so the newest snapshot is the last change - which is the more useful
        // of the two facts: "checked 22 hours ago" is true of every bank at
        // once, while "unchanged for a week" separates them.
        //
        // One grouped query over the rows already on the page, merged in after
        // the cache rather than inside it: the listing is cached for 30 minutes
        // and this is the part that must not go stale with it.
        $cached['items'] = $this->withLastChanged($cached['items']);

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $cached['items'] = array_values(array_filter(
                $cached['items'],
                fn (array $row) => str_contains(mb_strtolower((string) $row['organization_name']), $needle),
            ));
            $cached['total'] = count($cached['items']);
        }

        // Ranked over every matching row before anything is paged away, so
        // rank 1 is the best rate in the market rather than the best of the
        // ten currently on screen - which is what the star, the summary cards
        // and the savings line all claim to be about.
        $ranked = $this->rankRows(
            collect($cached['items'])->map(fn (array $row) => (object) $row)->all(),
            $intent,
        );

        // Rebuilt fresh every request (cheap - it's just wrapping the small
        // cached array), not itself cached: LengthAwarePaginator is an
        // object, and config/cache.php's 'serializable_classes' => false
        // means Redis can only round-trip the plain array above.
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
            // Somewhere to go when the current combination has no rows, so an
            // empty result is never a dead end.
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
            // Every geocoded branch of every organization on the page. Only
            // built for the map: it is a second query and a payload the list
            // has no use for.
            'mapBranches' => $viewMode === 'map' ? $this->mapBranches($cached['items']) : [],
            // Currencies the negotiation modal can actually offer: brokered
            // by us and served by at least one reachable office. Same rule as
            // the full page, so the two never disagree about what is possible.
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
            // Every matching row, ranked - what the page says about the market
            // is said about all of it.
            'ranked' => $ranked,
            // The ten of them this page shows.
            'pageRows' => $rates->items(),
            // Below this the transaction isn't large enough for an exchange
            // office to renegotiate, so the CTA states the bar instead of
            // inviting everyone to ask.
            'quoteMinimum' => config('exchange-quotes.minimum_amounts')[$selectedCurrency?->code] ?? null,
            'centralBankRate' => $this->centralBankRate($selectedCurrency),
            'hasLocation' => $hasLocation,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    /**
     * The rate column the visitor's intent actually ranks on: buying the
     * currency means they pay the organization's sell rate, selling it means
     * they receive its buy rate.
     */
    public static function rateFieldForIntent(string $intent): string
    {
        return $intent === 'sell' ? 'buy_rate' : 'sell_rate';
    }

    /**
     * The official reference rate for this currency, or null when we have not
     * scraped one. Several scrapers publish it, so this takes the most recent;
     * buy and sell are identical on these rows, which is why one figure is
     * enough.
     */
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
     * Banks and exchange offices sit in the same table on purpose: the visitor
     * wants the best rate, not a per-market league table. They quote very
     * different levels (~363 vs ~384 for USD cash), so each row carries its own
     * market badge and the market tabs still narrow to one or the other.
     *
     * Best is computed from the values rather than taken as the first row: the
     * visitor may have sorted by spread or distance, in which case row one is
     * not the best rate.
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

        // Rank by rate, independent of the visitor's chosen sort: they may have
        // ordered by spread or distance, in which case row one is not rank one.
        // Equal rates share a rank, so two joint-seconds are followed by a 4th.
        $ordered = $values->unique()->sort()->values();
        if ($intent === 'sell') {
            $ordered = $ordered->reverse()->values();
        }

        // Keyed by a fixed-precision string, not the float: PHP casts float
        // array keys to int, which would collapse 365.50 and 365.00 onto one.
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

    /**
     * First type with data that isn't the one already selected - used to offer
     * a way forward from an empty result rather than a bare "nothing found".
     */
    private function suggestedType(RateType $selectedType, Collection $availableTypes): ?string
    {
        return $availableTypes->first(fn (string $type) => $type !== $selectedType->value);
    }

    /**
     * Silently dropped rather than rejected when malformed or out of range -
     * the amount only switches on a second view of the same data, so a bad
     * value degrades to the plain rate table instead of erroring. Upper bound
     * matches the exchange-quote form's own cap.
     */
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
            'selectedOrgType' => $selectedOrgType, 'selectedCity' => $selectedCity, 'sort' => $sort, 'direction' => $direction,
            'openOrganizationIds' => $openOrganizationIds] = $filters;

        // Depends on both rate data and Organization::withRatingStats()
        // (review counts/averages), so it needs both tags - a review write
        // invalidates this without touching the simpler rate-only caches
        // above, and vice versa. Keyed on every filter input and the locale
        // (row URLs like organizations.show are locale-prefixed,
        // so a locale-less key would leak one locale's links into another's
        // cached render - see rates-table.blade.php for the same caveat).
        // 'v3' bumps every key at once: entries are now the whole filtered
        // set rather than one page of it, so an older entry would render as a
        // truncated market with the wrong best rate marked.
        $cacheKey = 'rates.listing.'.md5(json_encode([
            'v3', app()->getLocale(), $selectedCurrency?->id, $selectedType->value, $selectedOrganization?->id,
            $selectedOrgType, $selectedCity, $sort, $direction, $openOrganizationIds,
        ]));

        return Cache::tags([RateCache::TAG, OrgRatingsCache::TAG])->remember(
            $cacheKey,
            now()->addMinutes(30),
            function () use ($selectedCurrency, $selectedType, $selectedOrganization, $selectedOrgType, $selectedCity, $sort, $direction, $openOrganizationIds) {
                // Every matching row, not one page of them. Fourteen is the
                // most any currency and type has, and the page is ranked,
                // averaged and starred against the whole market - paging in
                // SQL would have made "best rate" mean "best on this page".
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
            'selectedOrgType' => $selectedOrgType, 'selectedCity' => $selectedCity, 'sort' => $sort, 'direction' => $direction,
            'openOrganizationIds' => $openOrganizationIds] = $filters;

        $rows = $this->baseQuery($selectedCurrency, $selectedType, $selectedOrganization, $selectedOrgType, $selectedCity, $openOrganizationIds)
            ->get()
            ->map(function (CurrencyRate $rate) use ($latitude, $longitude, $selectedCity) {
                // sortBy, not min(): the branch that won is the one to send
                // people to, and the distance alone does not say which it was.
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
            // Passed as a resolved timestamp rather than a period, so the
            // cache key and the query cannot disagree about where "today"
            // ends.
            // An empty list is a real answer - nothing is open - so this
            // checks for null rather than for emptiness.
            ->when($openOrganizationIds !== null, fn ($query) => $query->whereIn('organization_id', $openOrganizationIds))
            // Branches live inside this closure rather than as a separate
            // ->with('organization.branches'): declared afterwards, the nested
            // form replaces the constraint on 'organization' and silently drops
            // withRatingStats(), so every row's review count comes back null.
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

    /**
     * A rate belongs to an organization, but you walk to a branch - and nine
     * of fifteen organizations here have more than one. Directions are offered
     * only when a single branch is identifiable: the nearest one once location
     * is shared, the only one, or the only one in the selected city. Guessing
     * otherwise sends someone across town.
     */
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
            // Universal cross-platform link: iOS, Android and desktop all
            // resolve this to their own maps app rather than a web page.
            'url' => 'https://www.google.com/maps/dir/?api=1&destination='.$branch->latitude.','.$branch->longitude,
        ];
    }

    /**
     * Stamps each row with when its rate last changed, in one query for the
     * whole page. A rate with no history has never been seen to move since we
     * started recording, which is not the same as "never changed" - so it stays
     * null and the view simply says nothing rather than claiming otherwise.
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
     * Branches with no hours on file are excluded rather than assumed open:
     * "open now" is a promise that someone is standing behind a counter, and
     * we should not make it on a guess.
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
     * A rate belongs to an organization, not to a branch, so one row can put
     * several pins on the map - each showing the same rate at a different
     * address. Branches without coordinates are dropped rather than pinned at
     * the origin, which would put them in the Gulf of Guinea.
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
                    // A branch we know nothing about is not a closed one.
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
