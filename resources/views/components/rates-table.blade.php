@php
    // Query results are cached as a plain array by HomeRatesTableData (see
    // its docblock) - this component only assembles the already-computed
    // data for display, it doesn't query directly anymore.
    $homeRatesData = app(\App\Services\HomeRatesTableData::class)->build();
    $currencies = $homeRatesData['currencies'];
    $ratesByCurrency = $homeRatesData['ratesByCurrency'];
    $defaultCurrency = $homeRatesData['defaultCurrency'];
    $defaultRateType = $homeRatesData['defaultRateType'];
@endphp

@if (!empty($currencies))
    <section
        id="rates"
        x-data="{ tab: '{{ $defaultCurrency }}', rateTab: '{{ $defaultRateType }}' }"
        class="mx-auto max-w-7xl px-6 py-16 lg:px-10 scroll-mt-24"
    >
        <div class="lg:flex lg:items-start lg:gap-10">
        <div class="min-w-0 flex-1">
        <h2 class="font-heading text-2xl font-bold text-ink lg:text-3xl">
            {{ __('rates.heading') }}
        </h2>
        <p class="mt-2 max-w-2xl text-sm text-muted">
            {{ __('rates.subheading') }}
        </p>

        {{-- Currency tabs --}}
        <div class="mt-8 flex gap-1 overflow-x-auto border-b border-placeholder">
            @foreach ($currencies as $currency)
                <button
                    type="button"
                    @click="tab = '{{ $currency }}'"
                    :class="tab === '{{ $currency }}' ? 'bg-primary text-white' : 'text-muted hover:text-ink'"
                    class="shrink-0 px-4 py-3 text-xs font-semibold tracking-wide whitespace-nowrap uppercase transition"
                >
                    {{ $currency }}
                </button>
            @endforeach
        </div>

        {{-- Per-currency panels --}}
        @foreach ($currencies as $currency)
            <div
                x-show="tab === '{{ $currency }}'"
                @if ($currency !== $defaultCurrency) x-cloak @endif
                class="border border-t-0 border-placeholder"
            >
                @if (empty($ratesByCurrency[$currency]))
                    <p class="px-6 py-16 text-center text-sm text-muted">{{ __('rates.no_data') }}</p>
                @else
                    {{-- Rate-type sub-tabs --}}
                    <div class="flex flex-wrap gap-2 px-6 py-4">
                        @foreach ($ratesByCurrency[$currency] as $rateTypeValue => $rows)
                            <button
                                type="button"
                                @click="rateTab = '{{ $rateTypeValue }}'"
                                :class="rateTab === '{{ $rateTypeValue }}' ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink'"
                                class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                            >
                                {{ __('organizations.rate_types.' . $rateTypeValue) }}
                            </button>
                        @endforeach
                    </div>

                    @foreach ($ratesByCurrency[$currency] as $rateTypeValue => $rows)
                        <div
                            x-show="rateTab === '{{ $rateTypeValue }}'"
                            @if (!($currency === $defaultCurrency && $rateTypeValue === $defaultRateType)) x-cloak @endif
                            x-data="{
                                rows: @js($rows),
                                sortKey: 'sell_rate',
                                sortDir: 'asc',
                                toggleSort(key) {
                                    this.sortDir = this.sortKey === key && this.sortDir === 'asc' ? 'desc' : 'asc';
                                    this.sortKey = key;
                                },
                                get sorted() {
                                    return [...this.rows].sort(
                                        (a, b) => (a[this.sortKey] - b[this.sortKey]) * (this.sortDir === 'asc' ? 1 : -1)
                                    );
                                },
                            }"
                            class="overflow-hidden border-t border-placeholder"
                        >
                            {{-- Column header --}}
                            <div class="flex items-center gap-4 border-b border-placeholder bg-placeholder/20 px-6 py-2 text-xs font-semibold text-subtle uppercase">
                                <span class="w-8 shrink-0"></span>
                                <span class="w-10 shrink-0"></span>
                                <span class="min-w-0 flex-1"></span>
                                <button
                                    type="button"
                                    @click="toggleSort('buy_rate')"
                                    class="hidden w-20 items-center justify-end gap-1 text-right hover:text-ink sm:flex"
                                >
                                    {{ __('organizations.buy') }}
                                    <span x-show="sortKey === 'buy_rate'" x-text="sortDir === 'asc' ? '▲' : '▼'"></span>
                                </button>
                                <button
                                    type="button"
                                    @click="toggleSort('sell_rate')"
                                    class="flex w-20 items-center justify-end gap-1 text-right hover:text-ink"
                                >
                                    {{ __('organizations.sell') }}
                                    <span x-show="sortKey === 'sell_rate'" x-text="sortDir === 'asc' ? '▲' : '▼'"></span>
                                </button>
                                <span class="w-28 shrink-0 text-right whitespace-nowrap">{{ __('rates.alert_column') }}</span>
                            </div>

                            <template x-for="(row, index) in sorted" :key="row.id">
                                <div class="flex items-center gap-4 border-b border-placeholder px-6 py-5 last:border-b-0">
                                    <span
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                        :class="index === 0 ? 'bg-accent-yellow text-ink' : 'bg-placeholder/60 text-muted'"
                                        x-text="index + 1"
                                    ></span>

                                    <img
                                        x-show="row.logo"
                                        :src="row.logo"
                                        :alt="row.name"
                                        class="h-10 w-10 shrink-0 rounded-full object-contain"
                                    >
                                    <div
                                        x-show="!row.logo"
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                        x-text="row.initial"
                                    ></div>

                                    <div class="min-w-0 flex-1 overflow-hidden">
                                        <a
                                            :href="row.url"
                                            class="block truncate text-sm font-medium text-ink hover:text-primary"
                                            x-text="row.name"
                                        ></a>
                                        <div x-show="row.reviews_count > 0" class="mt-0.5 flex min-w-0 items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-3 w-3 shrink-0 fill-accent-yellow">
                                                <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                                            </svg>
                                            <span class="truncate text-xs text-subtle" x-text="row.rating.toFixed(1) + ' (' + row.reviews_count + ')'"></span>
                                        </div>
                                    </div>

                                    <div class="hidden w-20 text-right sm:block">
                                        <p class="font-heading text-lg font-bold text-primary" x-text="row.buy_rate.toFixed(2)"></p>
                                    </div>

                                    <div class="w-20 text-right">
                                        <p class="font-heading text-lg font-bold text-[#c25b6e]" x-text="row.sell_rate.toFixed(2)"></p>
                                    </div>

                                    <a
                                        :href="row.alertUrl"
                                        :title="'{{ __('rates.create_alert') }}'"
                                        class="flex w-6 shrink-0 items-center justify-end gap-1 whitespace-nowrap text-subtle hover:text-primary sm:w-28"
                                    >
                                        <span class="hidden text-xs font-medium sm:inline">{{ __('rates.alert_short') }}</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0">
                                            <path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0010 18z" clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                </div>
                            </template>
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach

        <div class="mt-6 text-center">
            <a href="{{ route('rates.index', ['currency' => $defaultCurrency]) }}" class="text-sm font-medium text-primary hover:underline">
                {{ __('rates.view_all') }} →
            </a>
        </div>
        </div>

        <x-ad-slot placement="home_rates" />
        </div>
    </section>
@endif
