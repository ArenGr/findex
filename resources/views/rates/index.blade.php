@extends('layouts.app')

@section('title', __('rates.all_heading') . ' — Findex')

@php
    use Illuminate\Support\Carbon;

    $baseParams = [
        'type' => $selectedType->value,
        'org_type' => $selectedOrgType,
        'organization' => $selectedOrganization?->slug,
        'city' => $selectedCity,
        'sort' => $sort,
        'dir' => $direction,
        'lat' => $latitude,
        'lng' => $longitude,
        'intent' => $intent,
        'amount' => $amount,
        'open' => $openNow ? 1 : null,
        'view' => $viewMode === 'map' ? 'map' : null,
        'q' => $search !== '' ? $search : null,
    ];

    $link = fn (array $overrides = []) => route('rates.index', array_filter(
        [...$baseParams, 'currency' => $selectedCurrency?->code, ...$overrides],
        fn ($value) => $value !== null && $value !== '',
    ));

    $hasNonDefaultFilter = $selectedType !== \App\Enums\RateType::CASH
        || $selectedOrgType || $selectedOrganization || $selectedCity || $hasLocation
        || $amount !== null || $openNow || $search !== '';

    $amd = fn (float $value) => $value < 1000
        ? number_format($value, 2)
        : number_format($value);

    $calculating = $amount !== null;
    $rateField = \App\Http\Controllers\RateController::rateFieldForIntent($intent);
    $isBuying = $intent === 'buy';

    $activeSortColumn = $sort === 'best' ? ($isBuying ? 'sell' : 'buy') : $sort;

    $naturalSort = ['buy' => 'desc', 'sell' => 'asc', 'spread' => 'asc', 'updated' => 'desc'];

    $sortHref = fn (string $column) => $link([
        'sort' => $column,
        'dir' => $activeSortColumn === $column
            ? ($direction === 'asc' ? 'desc' : 'asc')
            : $naturalSort[$column],
    ]);

    $amdCode = __('exchange_quotes.request.amd');
    $currencyCode = $selectedCurrency?->code;
    $sourceCode = $isBuying ? $amdCode : $currencyCode;
    $targetCode = $isBuying ? $currencyCode : $amdCode;

    $convert = fn (float $rate) => $isBuying ? $amount / $rate : $amount * $rate;

    $staleAfterHours = 24;
    $isStale = fn ($scrapedAt) => $scrapedAt && Carbon::parse($scrapedAt)->diffInHours(now()) >= $staleAfterHours;

    $rowCount = $ranked['count'];
    $allStale = $rowCount > 0 && collect($ranked['rows'])->every(fn ($row) => $isStale($row->scraped_at));

    $marketSaving = $calculating && $ranked['best_value'] !== null && $ranked['worst_value'] !== null
        ? abs($convert((float) $ranked['best_value']) - $convert((float) $ranked['worst_value']))
        : null;

    $bestRows = collect($ranked['rows'])->where('rank', 1);
    $best = $bestRows->first();

    $showMarket = collect($ranked['rows'])->pluck('organization_type')->unique()->count() > 1;

    $labelClass = 'block text-xs font-semibold tracking-wider text-muted uppercase';

    // The home page's card: rounded-2xl, a light border, shadow-sm.
    $cardClass = 'rounded-2xl border border-placeholder bg-white shadow-sm';

    $pillBase = 'inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg border px-4 text-sm font-medium transition';
    $pillOff = 'border-placeholder bg-white text-muted hover:border-primary hover:bg-primary/10 hover:text-primary';
    $pillOn = 'border-primary bg-primary text-white hover:bg-primary-dark';

    $alertPrefill = [
        'form' => [
            'currency_id' => (string) ($selectedCurrency?->id ?? ''),
            'organization_id' => (string) ($selectedOrganization?->id ?? ''),
            'rate_type' => $selectedType->value,
            'rate_field' => $rateField,
            'direction' => $isBuying ? 'below' : 'above',
            'threshold' => $best ? number_format((float) $best->{$rateField}, 2, '.', '') : '',
        ],
        'context' => [
            'currency' => __('exchange_quotes.request.amd'),
            'rate' => $best ? number_format((float) $best->{$rateField}, 2) : null,
        ],
    ];

    $on = fn(string $key) => \App\Support\Features::enabled($key);
    $alertsOn = $on('rate_alerts');
    $exchangeOn = $on('exchange');
    $historyOn = $on('rates_history');
    $mapOn = $on('rates_map');

    // route() throws once the route is gone, so this is only built when the
    // alerts feature is on.
    $alertHref = $alertsOn
        ? route('alerts.index', array_filter(['currency_id' => $selectedCurrency?->id])).'#create-alert'
        : null;

    $activeFilterCount = collect([
        $selectedType !== \App\Enums\RateType::CASH ? $selectedType : null,
        $selectedOrgType, $selectedOrganization, $selectedCity, $hasLocation ?: null, $openNow ?: null,
    ])->filter()->count();

@endphp

@section('content')
    {{-- Same hero as insurance and travel - see x-vertical-hero. The CTAs
         that used to live here are the page's own controls, and now sit with
         the rest of them below. --}}
    <x-vertical-hero
        icon="arrow-left-right"
        :eyebrow="__('nav.rates')"
        :title="__('rates.all_heading')"
        :subtitle="__('rates.all_subheading')"
        :signals="[
            ['icon' => 'building-2', 'title' => trans_choice('compare_ui.organizations', count($organizations), ['count' => count($organizations)]), 'sub' => __('rates.signal_organizations_sub')],
            ['icon' => 'clock', 'title' => __('rates.signal_fresh'), 'sub' => __('rates.signal_fresh_sub')],
            ['icon' => 'scale', 'title' => __('rates.signal_independent'), 'sub' => __('rates.signal_independent_sub')],
        ]"
    />


    <section id="rates-panel" class="site-container pt-9 pb-12">
        @php
            $everyday = config('rates.everyday');
            $everyday = is_array($everyday) && $everyday !== []
                ? $everyday
                : $currencies->pluck('code')->all();
            $currencyChip = fn ($currency) => $selectedCurrency?->id === $currency->id
                ? 'border-primary bg-primary text-white'
                : 'border-placeholder bg-white text-muted hover:border-primary hover:bg-primary/10 hover:text-primary';
            [$commonCurrencies, $otherCurrencies] = $currencies->partition(
                fn ($currency) => in_array($currency->code, $everyday, true)
            );
            $othersOpen = $otherCurrencies->contains(fn ($currency) => $selectedCurrency?->id === $currency->id);
        @endphp

        {{-- One collect panel, like the form on insurance and travel: what to
             convert, then how to narrow it. Everything the visitor answers
             before the results is inside this box. --}}
        <div class="rounded-3xl border border-border bg-surface p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
        <div x-data="{ showAll: @js($othersOpen) }" class="min-w-0">
            <span class="{{ $labelClass }}">{{ __('rates.currency_label') }}</span>
            {{-- On a phone the row scrolls sideways rather than wrapping. --}}
            <div class="mt-2 flex gap-2 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:overflow-visible [&::-webkit-scrollbar]:hidden">
                @foreach ($commonCurrencies as $currency)
                    <a
                        href="{{ $link(['currency' => $currency->code]) }}"
                        class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border px-4 text-sm font-semibold tracking-wide uppercase transition {{ $currencyChip($currency) }}"
                    >
                        <span aria-hidden="true" class="text-base">{{ \App\Models\Currency::flag($currency->code) }}</span>
                        {{ $currency->code }}
                    </a>
                @endforeach

                @if ($otherCurrencies->isNotEmpty())
                    @foreach ($otherCurrencies as $currency)
                        <a
                            href="{{ $link(['currency' => $currency->code]) }}"
                            x-show="showAll"
                            x-cloak
                            class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border px-4 text-sm font-semibold tracking-wide uppercase transition {{ $currencyChip($currency) }}"
                        >
                            <span aria-hidden="true" class="text-base">{{ \App\Models\Currency::flag($currency->code) }}</span>
                            {{ $currency->code }}
                        </a>
                    @endforeach

                    <button
                        type="button"
                        @click="showAll = !showAll"
                        :aria-expanded="showAll ? 'true' : 'false'"
                        class="inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-lg border border-placeholder bg-white px-4 text-sm font-medium text-muted transition hover:border-primary hover:bg-primary/10 hover:text-primary"
                    >
                        <span x-show="!showAll">+{{ $otherCurrencies->count() }}</span>
                        <span x-text="showAll ? @js(__('rates.currency_fewer')) : @js(__('rates.currency_more'))">{{ __('rates.currency_more') }}</span>
                    </button>
                @endif
            </div>

        </div>

            <div class="flex flex-wrap items-center gap-3">
    
                    @if ($quoteMinimum !== null && $exchangeOn)
                        @php $qualifies = $amount >= $quoteMinimum; @endphp
                        <a
                            @php
                                $handoverAmount = $amount === null
                                    ? null
                                    : ($isBuying && $best
                                        ? round($convert((float) $best->{$rateField}), 2)
                                        : $amount);
                            @endphp
                            href="{{ route('exchange.request', array_filter([
                                'currency' => $selectedCurrency?->code,
                                'amount' => $handoverAmount,
                                'city' => $selectedCity,
                                'rate_field' => $rateField,
                            ])) }}"
                            onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('better-rate-open', { detail: {{ Js::from([
                                'form' => [
                                    'currency_code' => (string) ($selectedCurrency?->code ?? ''),
                                    'amount' => $handoverAmount === null ? '' : (string) $handoverAmount,
                                    'rate_field' => $rateField,
                                    'preferred_city' => (string) ($selectedCity ?? ''),
                                ],
                                'context' => [
                                    'code' => (string) ($selectedCurrency?->code ?? ''),
                                    'rate' => $best ? number_format((float) $best->{$rateField}, 2) : null,
                                    'total' => $best && $handoverAmount ? $amd($handoverAmount * (float) $best->{$rateField}) : null,
                                ],
                            ]) }} }))"
                            class="btn btn-primary min-w-0"
                        >
                            <svg
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                                stroke-linecap="round" stroke-linejoin="round"
                                class="h-5 w-5 shrink-0" aria-hidden="true"
                            >
                                <path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2z" />
                                <path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1" />
                            </svg>
                            <span class="min-w-0 break-words">{{ __('rates.cta_button') }}</span>
                        </a>
    
                        <x-info-popover :label="__('rates.cta_button')">
                            <p class="font-semibold text-ink">
                                @if ($qualifies)
                                    {{ __('rates.cta_heading_qualified', ['amount' => number_format($amount), 'code' => $selectedCurrency?->code]) }}
                                @else
                                    {{ __('rates.cta_heading', ['amount' => number_format($quoteMinimum), 'code' => $selectedCurrency?->code]) }}
                                @endif
                            </p>
                            <p class="mt-2">{{ __('rates.cta_body') }}</p>
                            <p class="mt-2 text-xs">{{ __('rates.cta_note') }}</p>
                        </x-info-popover>
                    @endif
    
                    @if ($alertsOn)
                    <a
                        href="{{ $alertHref }}"
                        onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('rate-alert-open', { detail: {{ Js::from($alertPrefill) }} }))"
                        class="btn btn-secondary min-w-0"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-accent-yellow" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0010 18z" clip-rule="evenodd" />
                        </svg>
                        <span class="min-w-0 break-words">{{ __('rates.alert_cta') }}</span>
                    </a>
    
                    <x-info-popover :label="__('rates.alert_cta')">
                        {{ __('rates.alert_hint') }}
                    </x-info-popover>
                    @endif
                </div>
        </div>

        @if ($centralBankRate)
            <div class="mt-4 text-sm break-words text-muted">
                {{ __('rates.central_bank_reference', [
                    'rate' => number_format((float) $centralBankRate['rate'], 2),
                    'code' => $selectedCurrency?->code,
                ]) }}
            </div>
        @endif

        @php
            $bestBuy = collect($ranked['rows'])->max(fn ($row) => (float) $row->buy_rate);
            $bestSell = collect($ranked['rows'])->min(fn ($row) => (float) $row->sell_rate);
            $isBestRate = fn (float $value, ?float $target) => $target !== null && abs($value - $target) < 0.00005;

            $bestBuyCount = collect($ranked['rows'])->filter(fn ($row) => $isBestRate((float) $row->buy_rate, $bestBuy))->count();
            $bestSellCount = collect($ranked['rows'])->filter(fn ($row) => $isBestRate((float) $row->sell_rate, $bestSell))->count();
            $bestTotalCount = $bestRows->count();

            $totalColumn = __('rates.you_receive_column');

            $holderOf = function (callable $wins) use ($ranked) {
                $rows = collect($ranked['rows'])->filter($wins);

                if ($rows->count() > 1) {
                    return trans_choice('rates.best_shared', $rows->count() - 1, [
                        'name' => $rows->first()->organization_name,
                        'count' => $rows->count() - 1,
                    ]);
                }

                return $rows->first()?->organization_name;
            };

            $marketAverage = round(collect($ranked['rows'])->avg(fn ($row) => (float) $row->{$rateField}), 2);
            $averageGain = $calculating && $marketAverage
                ? $convert((float) $ranked['best_value']) - $convert((float) $marketAverage)
                : null;

            $organizationCount = collect($ranked['rows'])->pluck('organization_id')->unique()->count();
            $mapPoints = [];

            if ($viewMode === 'map') {
                foreach ($ranked['rows'] as $row) {
                    foreach ($mapBranches[$row->organization_id] ?? [] as $branch) {
                        $mapPoints[] = [
                            'lat' => $branch['lat'],
                            'lng' => $branch['lng'],
                            'name' => $row->organization_name,
                            'branch' => $branch['name'],
                            'address' => $branch['address'],
                            'rate' => number_format((float) $row->{$rateField}, 2),
                            'best' => $row->rank === 1,
                            'total' => $calculating ? $amd($convert((float) $row->{$rateField})).' '.$targetCode : null,
                            'distance' => $hasLocation && isset($row->distance_km)
                                ? __('rates.distance_km', ['km' => number_format($row->distance_km, 1)])
                                : null,
                            'open' => $branch['open'],
                            'openLabel' => $branch['open'] === null
                                ? __('rates.hours_unknown')
                                : ($branch['open'] ? __('rates.open') : __('rates.closed')),
                            'hours' => $branch['hours'],
                            'directions' => 'https://www.google.com/maps/dir/?api=1&destination='.$branch['lat'].','.$branch['lng'],
                            'negotiate' => $exchangeOn && $row->organization_type === 'exchange' && $quoteMinimum !== null
                                ? route('exchange.request', array_filter([
                                    'currency' => $selectedCurrency?->code,
                                    'amount' => $amount,
                                    'city' => $branch['city'],
                                    'rate_field' => $rateField,
                                ]))
                                : null,
                        ];
                    }
                }
            }

            $summaryCards = [
                [
                    'label' => __('rates.summary_best_buy'),
                    'value' => $bestBuy,
                    'note' => $holderOf(fn ($row) => $isBestRate((float) $row->buy_rate, $bestBuy)),
                    'hint' => __('rates.buy_hint'),
                    'variant' => 'buy',
                    'badge' => __('rates.summary_best_value'),
                ],
                [
                    'label' => __('rates.summary_best_sell'),
                    'value' => $bestSell,
                    'note' => $holderOf(fn ($row) => $isBestRate((float) $row->sell_rate, $bestSell)),
                    'hint' => __('rates.sell_hint'),
                    'variant' => 'sell',
                    'badge' => null,
                ],
                [
                    'label' => __('rates.summary_average'),
                    'value' => $marketAverage,
                    'note' => trans_choice('rates.summary_across', $organizationCount, ['count' => $organizationCount]),
                    'hint' => __('rates.summary_average_hint'),
                    'variant' => 'neutral',
                    'badge' => null,
                ],
            ];
        @endphp

        <div class="mt-5 border-t border-border pt-5">
            <div class="flex flex-col gap-3 xl:flex-row xl:flex-wrap xl:items-stretch">
                @if ($viewMode !== 'map')
                    <form method="GET" action="{{ route('rates.index') }}" class="flex min-w-0 flex-1 items-center gap-2 rounded-xl border border-placeholder px-4 xl:min-w-[15rem]">
                        @foreach (['currency', 'type', 'org_type', 'organization', 'city', 'lat', 'lng', 'intent', 'amount', 'sort', 'dir', 'open'] as $carried)
                            @php $value = $carried === 'currency' ? $selectedCurrency?->code : ($baseParams[$carried] ?? null); @endphp
                            @if (! empty($value))<input type="hidden" name="{{ $carried }}" value="{{ $value }}">@endif
                        @endforeach
                        <label for="q" class="sr-only">{{ __('rates.search_label') }}</label>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-muted" aria-hidden="true">
                            <circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" />
                        </svg>
                        <input
                            type="search" name="q" id="q" value="{{ $search }}"
                            placeholder="{{ __('rates.search_placeholder') }}" autocomplete="off"
                            @input.debounce.350ms="if ($el.value.trim().length >= 3 || $el.value.trim() === '') $el.form.requestSubmit()"
                            @search="$el.form.requestSubmit()"
                            class="h-14 w-full min-w-0 bg-transparent text-sm text-ink outline-none"
                        >
                        <button type="submit" class="sr-only">{{ __('rates.search_label') }}</button>
                    </form>
                @endif

                @if ($orgTypes->count() > 1)
                    <x-rates.filter-menu
                        :active="$selectedOrgType !== null"
                        :label="__('rates.market_label')"
                        :options="[
                            ['label' => __('rates.market_all'), 'href' => $link(['org_type' => null, 'organization' => null]), 'selected' => $selectedOrgType === null],
                            ...$orgTypes->map(fn ($orgType) => [
                                'label' => __('rates.markets.' . $orgType),
                                'href' => $link(['org_type' => $orgType, 'organization' => null]),
                                'selected' => $selectedOrgType === $orgType,
                            ])->all(),
                        ]"
                    />
                @endif

                @if (collect($availableTypes)->isNotEmpty())
                    <x-rates.filter-menu
                        :label="__('rates.type_label')"
                        :options="collect($availableTypes)->map(fn ($typeValue) => [
                            'label' => __('organizations.rate_types.' . $typeValue),
                            'href' => $link(['type' => $typeValue]),
                            'selected' => $selectedType->value === $typeValue,
                        ])->all()"
                    />
                @endif

                @if ($selectedOrgType !== null && $organizations->isNotEmpty())
                    <x-rates.filter-menu
                        searchable
                        :active="$selectedOrganization !== null"
                        :label="__('rates.filter_org.' . $selectedOrgType)"
                        :options="[
                            ['label' => __('rates.filter_org_all.' . $selectedOrgType), 'href' => $link(['organization' => null]), 'selected' => $selectedOrganization === null],
                            ...$organizations->map(fn ($organization) => [
                                'label' => $organization->name,
                                'href' => $link(['organization' => $organization->slug]),
                                'selected' => $selectedOrganization?->id === $organization->id,
                            ])->all(),
                        ]"
                    />
                @endif

                @if ($cities->isNotEmpty())
                    <x-rates.filter-menu
                        :active="$selectedCity !== null"
                        :label="__('rates.filter_city')"
                        :options="[
                            ['label' => __('rates.filter_city_all'), 'href' => $link(['city' => null]), 'selected' => $selectedCity === null],
                            ...$cities->map(fn ($city) => [
                                'label' => $city,
                                'href' => $link(['city' => $city]),
                                'selected' => $selectedCity === $city,
                            ])->all(),
                        ]"
                    />
                @endif

                <div class="flex flex-wrap items-center gap-2 xl:ms-auto">
                    <a
                        href="{{ $link(['open' => $openNow ? null : 1]) }}"
                        aria-pressed="{{ $openNow ? 'true' : 'false' }}"
                        class="{{ $pillBase }} {{ $openNow ? $pillOn : $pillOff }}"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
                        </svg>
                        {{ __('rates.open_now') }}
                    </a>

                    <div x-data="{
                            state: 'idle',
                            findNearby() {
                                if (!navigator.geolocation) { this.state = 'error'; return; }
                                this.state = 'locating';
                                navigator.geolocation.getCurrentPosition(
                                    (position) => {
                                        const url = new URL(window.location.href);
                                        url.searchParams.set('lat', position.coords.latitude);
                                        url.searchParams.set('lng', position.coords.longitude);
                                        url.searchParams.set('sort', 'distance');
                                        url.searchParams.set('direction', 'asc');
                                        window.dispatchEvent(new CustomEvent('rates:navigate', { detail: url.toString() }));
                                    },
                                    () => { this.state = 'error'; },
                                );
                            },
                        }">
                        @if ($hasLocation)
                            <a
                                href="{{ $link(['lat' => null, 'lng' => null, 'sort' => null, 'dir' => null]) }}"
                                class="{{ $pillBase }} {{ $pillOn }}"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-primary" aria-hidden="true">
                                    <path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0" /><circle cx="12" cy="10" r="3" />
                                </svg>
                                {{ __('rates.nearby_active') }}
                                <span aria-hidden="true" class="text-muted">&times;</span>
                            </a>
                        @else
                            <button
                                type="button" @click="findNearby()" :disabled="state === 'locating'"
                                class="{{ $pillBase }} {{ $pillOff }} disabled:opacity-60"
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0 text-accent-red" aria-hidden="true">
                                    <path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0" /><circle cx="12" cy="10" r="3" />
                                </svg>
                                <span x-show="state !== 'locating'">{{ __('rates.find_nearby') }}</span>
                                <span x-show="state === 'locating'" x-cloak>{{ __('rates.locating') }}</span>
                            </button>
                            <p x-show="state === 'error'" x-cloak class="mt-1 text-xs text-red-600">{{ __('rates.location_error') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if ($search !== '')
                <a href="{{ $link(['q' => null]) }}" class="mt-2 inline-block px-1 text-xs text-muted underline hover:text-ink">{{ __('rates.search_clear') }}</a>
            @endif
        </div>
        </div>

        @if ($rowCount > 0)
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    @foreach ($summaryCards as $card)
                        <x-rates.summary-card
                            :label="$card['label']"
                            :value="$card['value']"
                            :note="$card['note']"
                            :hint="$card['hint']"
                            :variant="$card['variant']"
                            :badge="$card['badge']"
                        />
                    @endforeach
                </div>
                @if ($historyOn)
                    <p class="mt-3">
                        <a href="{{ route('rates.history', ['currency' => $selectedCurrency?->code]) }}" class="inline-flex min-h-11 items-center text-sm font-medium break-words text-primary hover:underline">
                            {{ __('rates.history.link') }} &rarr;
                        </a>
                    </p>
                @endif
            @if ($calculating && $best)
                <div class="relative mt-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-4 overflow-hidden rounded-2xl border-2 border-primary/40 bg-primary/5 py-5 pr-4 pl-6 sm:pr-6 sm:pl-8">
                    <span class="absolute inset-y-0 left-0 w-2 bg-primary" aria-hidden="true"></span>

                    <div class="flex min-w-0 items-center gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-placeholder bg-white shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-6 w-6 fill-accent-yellow" aria-hidden="true">
                                <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <p class="font-heading text-lg font-bold break-words text-ink">
                                {{ $isStale($best->scraped_at) ? __('rates.best_heading_stale') : __('rates.best_heading') }}
                            </p>
                            <p class="text-sm break-words text-muted">
                                @if ($bestRows->count() > 1)
                                    {{ trans_choice('rates.best_shared', $bestRows->count() - 1, [
                                        'name' => $best->organization_name,
                                        'count' => $bestRows->count() - 1,
                                    ]) }}
                                @else
                                    {{ $best->organization_name }}
                                @endif
                                @if ($best->scraped_at)
                                    &middot; {{ Carbon::parse($best->scraped_at)->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="text-end">
                        <p class="{{ $labelClass }}">{{ $totalColumn }}</p>
                        <p class="mt-1 text-2xl font-bold tracking-tight whitespace-nowrap text-ink tabular-nums sm:text-4xl">
                            {{ $amd($convert((float) $best->{$rateField})) }}
                            <span class="text-base font-normal text-muted sm:text-xl">{{ $targetCode }}</span>
                        </p>
                        @if ($averageGain !== null && $averageGain >= 0.01)
                            <p class="mt-1 text-sm break-words text-muted">
                                {{ __('rates.above_average', ['amount' => $amd($averageGain), 'currency' => $targetCode]) }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif
            <div class="mt-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
                <p class="min-w-0 text-sm break-words text-muted empty:hidden">
                    @if ($hasNonDefaultFilter)
                        <a href="{{ route('rates.index', array_filter(['currency' => $selectedCurrency?->code])) }}" class="-my-2 inline-block py-2 underline hover:text-ink">{{ __('rates.reset_filters') }}</a>
                    @endif
                    @if ($marketSaving !== null && $marketSaving >= 0.01)
                        {{ __('rates.market_saving_sell', [
                            'amount' => $amd($marketSaving),
                            'currency' => $targetCode,
                        ]) }}
                    @endif
                    @if ($allStale)
                        <span class="inline-flex items-center gap-1 text-[#B4791F]">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5 shrink-0" aria-hidden="true">
                                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0" />
                                <path d="M12 9v4" /><path d="M12 17h.01" />
                            </svg>
                            {{ __('rates.all_stale_notice') }}
                        </span>
                    @endif
                </p>
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    {{-- Sorting used to live in the table's column headings.
                         The table is gone, so it sits above the list where the
                         other compare pages put it. --}}
                    @if ($viewMode !== 'map')
                        <label for="rates-sort" class="text-xs text-muted">{{ __('compare_ui.sort_by') }}</label>
                        <select
                            id="rates-sort"
                            {{-- A synthetic link click, so the panel's fetch-and-morph handler takes it
                                 like every other control here instead of reloading the page. --}}
                            onchange="const a = document.createElement('a'); a.href = this.value; this.parentElement.appendChild(a); a.click(); a.remove();"
                            class="field h-11 min-h-0 w-auto min-w-0 py-2 pr-8 pl-3 text-sm font-medium"
                        >
                            @foreach (['buy', 'sell', 'spread', 'updated'] as $column)
                                @foreach (['desc', 'asc'] as $dir)
                                    <option
                                        value="{{ $link(['sort' => $column, 'dir' => $dir]) }}"
                                        @selected($activeSortColumn === $column && $direction === $dir)
                                    >{{ __('rates.sort_'.$dir, ['column' => __('rates.'.$column.'_column')]) }}</option>
                                @endforeach
                            @endforeach
                            @if ($hasLocation)
                                <option value="{{ $link(['sort' => 'distance', 'dir' => 'asc']) }}" @selected($activeSortColumn === 'distance')>{{ __('rates.sort_distance') }}</option>
                            @endif
                        </select>
                    @endif

                    {{-- Not rendered at all rather than hidden with a class:
                         a list/map switch with one option is not a switch. --}}
                    @if ($mapOn)
                    <div class="flex rounded-lg border border-placeholder bg-placeholder/25 p-1">
                        @foreach (['view_list' => null, 'view_map' => 'map'] as $key => $mode)
                            @php $isCurrent = $viewMode === ($mode ?? 'list'); @endphp
                            <a
                                href="{{ $link(['view' => $mode]) }}"
                                aria-current="{{ $isCurrent ? 'true' : 'false' }}"
                                class="inline-flex min-h-9 min-w-0 items-center rounded-md px-3 py-2 text-xs font-medium break-words transition {{ $isCurrent ? 'bg-primary text-white shadow-sm' : 'text-muted hover:text-ink' }}"
                            >
                                {{ __('rates.'.$key) }}
                            </a>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>

            @if ($viewMode === 'map')
                @if ($mapPoints === [])
                    <div class="mt-4 rounded-xl border border-dashed border-placeholder px-6 py-16 text-center">
                        <p class="text-sm text-muted">{{ __('rates.map_empty') }}</p>
                    </div>
                @else
                    <div data-rates-map class="mt-4">
                        <script type="application/json" data-rates-map-payload>
                            {!! json_encode([
                                'points' => $mapPoints,
                                'labels' => [
                                    'rate' => __('rates.map_rate_label'),
                                    'total' => __('rates.map_total_label'),
                                    'distance' => __('rates.map_distance_label'),
                                    'directions' => __('rates.directions'),
                                    'negotiate' => __('rates.cta_button'),
                                ],
                            ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}
                        </script>

                        <div
                            data-rates-map-canvas
                            class="h-[28rem] w-full overflow-hidden rounded-xl border border-placeholder bg-placeholder/20 sm:h-[34rem]"
                            role="application"
                            aria-label="{{ __('rates.view_map') }}"
                        ></div>
                    </div>
                @endif
            @else
            {{-- One card per rate at every width. The table and the separate
                 phone cards it replaced were two markups for the same rows;
                 sorting moved to the control above the list. --}}
            <div data-rates-list class="mt-5 flex flex-col gap-3">
                @foreach ($pageRows as $rate)
                    <x-rates.result-card
                        :rate="$rate"
                        :best="$calculating ? $rate->rank === 1 : $isBestRate((float) $rate->buy_rate, $bestBuy)"
                        :badge="__('rates.best_badge')"
                        :best-count="$calculating ? $bestTotalCount : $bestBuyCount"
                        :total="$calculating ? $amd($convert((float) $rate->{$rateField})) : null"
                        :total-label="$totalColumn"
                        :stale="$isStale($rate->scraped_at)"
                        :show-market="$showMarket"
                        :distance="$hasLocation && isset($rate->distance_km) ? __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) : null"
                    />
                @endforeach
            </div>
            @endif
            @if ($viewMode !== 'map')
                <x-rates.pagination :paginator="$rates" />
            @endif
        @else
            <div class="mt-8 rounded-2xl border border-dashed border-placeholder px-6 py-16 text-center">
                <p class="text-sm break-words text-muted">
                    {{ $search !== '' ? __('rates.search_empty', ['term' => $search]) : __('rates.no_rates_match') }}
                </p>

                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                    @if ($search !== '')
                        <a href="{{ $link(['q' => null]) }}" class="btn btn-primary">
                            {{ __('rates.search_clear') }}
                        </a>
                    @elseif ($suggestedType)
                        <a href="{{ $link(['type' => $suggestedType]) }}" class="btn btn-primary">
                            {{ __('rates.try_other_type', ['type' => __('organizations.rate_types.' . $suggestedType)]) }}
                        </a>
                    @endif
                    @if ($hasNonDefaultFilter)
                        <a href="{{ route('rates.index', array_filter(['currency' => $selectedCurrency?->code])) }}" class="text-sm font-medium text-primary hover:underline">
                            {{ __('rates.reset_filters') }}
                        </a>
                    @endif
                </div>
            </div>
        @endif

        <p class="mt-8 border-t border-placeholder pt-5 text-xs leading-relaxed break-words text-muted">
            {{ __('rates.disclaimer') }}
        </p>

    </section>

    <x-how-it-works
        :heading="__('rates.works_heading')"
        :sub="__('rates.works_sub')"
        :steps="[
            ['icon' => 'arrow-left-right', 'title' => __('rates.works_1_title'), 'body' => __('rates.works_1_body')],
            ['icon' => 'scale', 'title' => __('rates.works_2_title'), 'body' => __('rates.works_2_body')],
            ['icon' => 'bell', 'title' => __('rates.works_3_title'), 'body' => __('rates.works_3_body')],
            ['icon' => 'map-pin', 'title' => __('rates.works_4_title'), 'body' => __('rates.works_4_body')],
        ]"
    />

    <x-partners-strip :partners="$partners" :heading="__('rates.partners_heading')" marquee />
    @if ($alertsOn)
        <x-rate-alert-modal
            :currencies="$currencies"
            :organizations="$alertOrganizations"
            :rate-types="$alertRateTypes"
        />
    @endif
    @if ($exchangeOn)
        <x-better-rate-modal :currencies="$quoteCurrencies" :cities="$cities->all()" />
    @endif
    <script>
        (() => {
            const panel = document.getElementById('rates-panel');
            if (!panel || !window.fetch || !window.history.pushState || !window.DOMParser) {
                return;
            }

            let inFlight = null;

            const swap = async (url, push) => {
                inFlight?.abort();
                const request = new AbortController();
                inFlight = request;

                panel.setAttribute('aria-busy', 'true');

                const focusedId = document.activeElement?.id || null;

                try {
                    const response = await fetch(url, {
                        headers: { 'X-Requested-With': 'fetch' },
                        signal: request.signal,
                    });
                    if (!response.ok) {
                        throw new Error(response.status);
                    }

                    const next = new DOMParser()
                        .parseFromString(await response.text(), 'text/html')
                        .getElementById('rates-panel');
                    if (!next) {
                        throw new Error('no panel in response');
                    }

                    window.Alpine.morph(panel, next.outerHTML);

                    if (focusedId) {
                        document.getElementById(focusedId)?.focus({ preventScroll: true });
                    }
                    window.dispatchEvent(new CustomEvent('rates:panel-updated'));

                    if (push) {
                        window.history.pushState({ ratesUrl: url }, '', url);
                    }
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    window.location.assign(url);
                    return;
                } finally {
                    if (inFlight === request) {
                        inFlight = null;
                        panel.removeAttribute('aria-busy');
                    }
                }
            };

            panel.addEventListener('click', (event) => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }
                const link = event.target.closest('a[href]');
                if (!link || link.target) {
                    return;
                }
                const target = new URL(link.href, window.location.href);
                if (target.origin !== window.location.origin || target.pathname !== window.location.pathname) {
                    return;
                }
                event.preventDefault();
                swap(target.href, true);
            });

            panel.addEventListener('submit', (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'get') {
                    return;
                }
                const target = new URL(form.action, window.location.href);
                if (target.pathname !== window.location.pathname) {
                    return;
                }
                event.preventDefault();

                // Empty selects would otherwise litter the URL with bare keys.
                const params = new URLSearchParams();
                for (const [key, value] of new FormData(form)) {
                    if (value !== '') {
                        params.append(key, value);
                    }
                }
                target.search = params.toString();
                swap(target.href, true);
            });

            window.addEventListener('rates:navigate', (event) => swap(event.detail, true));
            window.addEventListener('popstate', () => swap(window.location.href, false));
        })();
    </script>
@endsection
