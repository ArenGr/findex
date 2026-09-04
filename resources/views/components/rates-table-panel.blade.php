@props(['rows', 'rateTypeValue', 'active'])

{{--
    One currency/rate-type panel of the homepage rates table.

    In its own file rather than inline: the homepage renders 53 of these, and
    at the nesting depth of rates-table.blade.php each one carried well over a
    kilobyte of pure indentation.

    Only the two numbers a sort can key on travel as JSON. The rows are
    rendered below by Blade - they used to exist solely as a JSON blob
    fed to <template x-for>, which meant the server shipped every name, URL,
    logo and rating as escaped JSON, painted an empty table, and then grew it
    by ~520px the moment Alpine booted. That was the page-wide jump this
    component was responsible for on every refresh.
--}}
<div
    x-show="rateTab === @js($rateTypeValue)"
    @unless ($active) x-cloak @endunless
    x-data="homeRatesPanel(@js(array_map(
        fn ($row) => ['buy_rate' => $row['buy_rate'], 'sell_rate' => $row['sell_rate']],
        $rows
    )))"
    class="overflow-hidden border-t border-placeholder"
>
    {{-- Column header --}}
    <div class="flex items-center gap-4 border-b border-placeholder bg-placeholder/20 px-6 py-2 text-xs font-semibold text-subtle uppercase">
        <span class="w-8 shrink-0"></span>
        <span class="w-10 shrink-0"></span>
        <span class="min-w-0 flex-1"></span>
        <button type="button" @click="toggleSort('buy_rate')" class="flex w-20 items-center justify-end gap-1 text-right hover:text-ink">
            {{ __('organizations.buy') }}
            {{-- x-cloak: the table ships sorted by sell rate, so this arrow starts
                 hidden and has to stay hidden until Alpine can say otherwise. --}}
            <span x-show="sortKey === 'buy_rate'" x-cloak x-text="sortDir === 'asc' ? '▲' : '▼'"></span>
        </button>
        <button type="button" @click="toggleSort('sell_rate')" class="flex w-20 items-center justify-end gap-1 text-right hover:text-ink">
            {{ __('organizations.sell') }}
            <span x-show="sortKey === 'sell_rate'" x-text="sortDir === 'asc' ? '▲' : '▼'">▲</span>
        </button>
        <span class="hidden w-24 shrink-0 text-right whitespace-nowrap sm:block" title="{{ __('rates.spread_hint') }}">{{ __('rates.spread_column') }}</span>
    </div>

    {{-- flex-col so each row can be placed with CSS `order`; -mb-px hides the
         last row's bottom border under the panel border (the old
         last:border-b-0 keyed off DOM order, which is no longer the order the
         rows are painted in). --}}
    <div class="-mb-px flex flex-col">
        @foreach ($rows as $i => $row)
            <x-rates-table-row :row="$row" :index="$i" />
        @endforeach
    </div>
</div>
