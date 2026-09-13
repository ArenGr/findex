@php
    $homeRatesData = app(\App\Services\HomeRatesTableData::class)->build();
    $currencies = $homeRatesData['currencies'];
    $ratesByCurrency = $homeRatesData['ratesByCurrency'];
    $defaultCurrency = $homeRatesData['defaultCurrency'];
    $defaultRateType = $homeRatesData['defaultRateType'];
    $alertUrlByCurrency = $homeRatesData['alertUrlByCurrency'];
@endphp

@if (!empty($currencies))
    <section
        id="rates"
        x-data="{ tab: @js($defaultCurrency), rateTab: @js($defaultRateType), alertUrlByCurrency: @js($alertUrlByCurrency) }"
        class="site-container py-16 scroll-mt-24"
    >
        <div class="lg:flex lg:items-start lg:gap-10">
        <div class="min-w-0 flex-1">
        {{-- One alert entry point for the whole table (not one per row) - follows the currently-selected currency tab via alertUrlByCurrency. --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="font-heading text-2xl font-bold text-ink lg:text-3xl">
                    {{ __('rates.heading') }}
                </h2>
                <p class="mt-2 max-w-2xl text-sm text-muted">
                    {{ __('rates.subheading') }}
                </p>
            </div>

            <a
                :href="alertUrlByCurrency[tab]"
                class="inline-flex shrink-0 items-center gap-1.5 text-sm font-medium text-ink hover:text-primary"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6 shrink-0 text-accent-yellow">
                    <path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0010 18z" clip-rule="evenodd" />
                </svg>
                {{ __('rates.alert_cta') }}
            </a>
        </div>

        <svg width="0" height="0" aria-hidden="true" class="absolute"><symbol id="findex-star" viewBox="0 0 20 20"><path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" /></symbol></svg>

        {{-- Currency tabs --}}
        <div class="mt-8 flex gap-1 overflow-x-auto border-b border-placeholder">
            @foreach ($currencies as $currency)
                <button
                    type="button"
                    @click="tab = @js($currency)"
                    :class="tab === @js($currency) ? 'bg-primary text-white' : 'text-muted hover:text-ink'"
                    class="shrink-0 px-4 py-3 text-xs font-semibold tracking-wide whitespace-nowrap uppercase transition"
                >
                    {{ $currency }}
                </button>
            @endforeach
        </div>

        {{-- Per-currency panels --}}
        @foreach ($currencies as $currency)
            <div
                x-show="tab === @js($currency)"
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
                                @click="rateTab = @js($rateTypeValue)"
                                :class="rateTab === @js($rateTypeValue) ? 'bg-ink text-white' : 'bg-placeholder/40 text-muted hover:text-ink'"
                                class="rounded-full px-3 py-1.5 text-xs font-medium transition"
                            >
                                {{ __('organizations.rate_types.' . $rateTypeValue) }}
                            </button>
                        @endforeach
                    </div>

                    @foreach ($ratesByCurrency[$currency] as $rateTypeValue => $rows)
                        <x-rates-table-panel
                            :rows="$rows"
                            :rate-type-value="$rateTypeValue"
                            :active="$currency === $defaultCurrency && $rateTypeValue === $defaultRateType"
                        />
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
