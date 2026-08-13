@extends('layouts.app')

@section('title', __('rates.all_heading') . ' — Findex')

@php
    use Illuminate\Support\Carbon;

    // Carries the current state onto every link/form on this page so changing
    // one control never silently resets the others.
    $baseParams = [
        'type' => $selectedType->value,
        'org_type' => $selectedOrgType,
        'organization' => $selectedOrganization?->slug,
        'city' => $selectedCity,
        'sort' => $sort,
        'direction' => $direction,
        'lat' => $latitude,
        'lng' => $longitude,
        'intent' => $intent,
        'amount' => $amount,
        'both' => $detailed ? 1 : null,
    ];

    $link = fn (array $overrides = []) => route('rates.index', array_filter(
        [...$baseParams, 'currency' => $selectedCurrency?->code, ...$overrides],
        fn ($value) => $value !== null && $value !== '',
    ));

    $hasNonDefaultFilter = $selectedType !== \App\Enums\RateType::CASH
        || $selectedOrgType || $selectedOrganization || $selectedCity || $hasLocation
        || $amount !== null;

    // Whole dram above 1,000, where a rounded half-unit is noise. Below it the
    // decimals are the number: 1 KZT at 4.60 rendered as "5", and the office
    // beside it quoting 4.75 rendered as "5" too, so the table stopped telling
    // two rows apart at exactly the amounts where the gap is easiest to see.
    $amd = fn (float $value) => $value < 1000
        ? number_format($value, 2)
        : number_format($value);

    // One table either way. Without an amount it answers "what are today's
    // rates"; with one it answers "what do I get", by gaining a column rather
    // than by becoming a different table.
    $calculating = $amount !== null;

    // Which column the visitor's intent ranks on, and therefore which one the
    // total is derived from.
    $rateField = \App\Http\Controllers\RateController::rateFieldForIntent($intent);
    $isBuying = $intent === 'buy';

    // "I have X, I want Y" instead of Buy/Sell. Buy and sell are written from
    // the institution's side - the column headed "Sell" is the one a buyer pays
    // - and asking a visitor to decode that is the oldest trap on this page.
    // The backend still speaks buy/sell; only the wording here changed.
    //
    // The amount is denominated in whatever the visitor HAS, which means the
    // arithmetic differs by direction rather than only the column it reads:
    // handing over foreign currency multiplies by the organization's buy rate,
    // and handing over dram divides by its sell rate.
    $amdCode = __('exchange_quotes.request.amd');
    $currencyCode = $selectedCurrency?->code;
    $sourceCode = $isBuying ? $amdCode : $currencyCode;
    $targetCode = $isBuying ? $currencyCode : $amdCode;

    $convert = fn (float $rate) => $isBuying ? $amount / $rate : $amount * $rate;

    // A rate this old is worth flagging - banks republish through the day, so
    // anything past a day is no longer "today's rate".
    $staleAfterHours = 24;
    $isStale = fn ($scrapedAt) => $scrapedAt && Carbon::parse($scrapedAt)->diffInHours(now()) >= $staleAfterHours;

    $rowCount = $ranked['count'];
    $allStale = $rowCount > 0 && collect($ranked['rows'])->every(fn ($row) => $isStale($row->scraped_at));

    // Shown once above the table rather than per market, now that everything is
    // ranked as one list. Computed from the two converted totals rather than
    // from the rate spread: when the visitor is handing over dram the total is a
    // division, so amount x spread would be the wrong number entirely.
    $marketSaving = $calculating && $ranked['best_value'] !== null && $ranked['worst_value'] !== null
        ? abs($convert((float) $ranked['best_value']) - $convert((float) $ranked['worst_value']))
        : null;

    // Rank is computed independently of the chosen sort, so the winner is the
    // same row whichever column the visitor ordered by. Resolved up here rather
    // than beside the table, because the alert trigger above it prefills a
    // threshold from the going rate.
    $bestRows = collect($ranked['rows'])->where('rank', 1);
    $best = $bestRows->first();

    // Banks and exchange offices share one table, so each row says which it is
    // - but only when the list actually mixes them.
    $showMarket = collect($ranked['rows'])->pluck('organization_type')->unique()->count() > 1;


    $labelClass = 'block text-xs font-semibold tracking-wider text-muted uppercase';

    // What the modal opens with. Hoisted because two things trigger it - the
    // chip above the table and the card below it - and a visitor who used one
    // and then the other should not get a different form.
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

    $alertHref = route('alerts.index', array_filter(['currency_id' => $selectedCurrency?->id])).'#create-alert';

    // Everything that has moved off its default, so the button can say how
    // much is hidden behind it. The transaction type counts too - "Card" is a
    // narrower view than "Cash" and the visitor should be told they set it.
    $activeFilterCount = collect([
        $selectedType !== \App\Enums\RateType::CASH ? $selectedType : null,
        $selectedOrgType, $selectedOrganization, $selectedCity, $hasLocation ?: null,
    ])->filter()->count();

@endphp

@section('content')
    <section id="rates-panel" class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        {{--
            Heading on the left, the page's two offers on the right, baselines
            aligned. They sat here before as plain text at the same ink, size and
            weight as the paragraph beside them, which nobody read as something
            you could press - so they are buttons now, one filled and one
            outlined. Two solid greens side by side would make the visitor choose
            between them, when negotiating is the offer no competitor can match
            and watching a rate is the fallback for everyone else.

            Stacked below md: at 390px the heading and two buttons cannot share
            a row without the buttons shrinking below their own labels.
        --}}
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div class="min-w-0">
                <h1 class="font-heading text-3xl leading-tight font-bold break-words text-ink">{{ __('rates.all_heading') }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-muted">{{ __('rates.all_subheading') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-3 md:shrink-0">
                @if ($quoteMinimum !== null)
                    @php $qualifies = $amount >= $quoteMinimum; @endphp
                    <a
                        href="{{ route('exchange.request', array_filter(['currency' => $selectedCurrency?->code, 'amount' => $qualifies ? $amount : null])) }}"
                        class="inline-flex min-w-0 items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-dark"
                    >
                        {{-- Two speech bubbles. Not exchange arrows, which read
                        as "swap currency" - what the whole page already does -
                        and not a handshake, whose five overlapping strokes
                        collapse into a blob at this size. Not a percent badge
                        either: that promises a discount, and all we can promise
                        is that the question gets asked. --}}
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

                    {{-- Who this is for, kept out of the button: it only matters
                    to the people who stop to ask. --}}
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

                <a
                    href="{{ $alertHref }}"
                    onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('rate-alert-open', { detail: {{ Js::from($alertPrefill) }} }))"
                    class="inline-flex min-w-0 items-center gap-2 rounded-lg border border-placeholder bg-white px-4 py-2 text-sm font-medium text-ink transition hover:bg-placeholder/25"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-accent-yellow" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a2.5 2.5 0 002.45-2h-4.9A2.5 2.5 0 0010 18z" clip-rule="evenodd" />
                    </svg>
                    <span class="min-w-0 break-words">{{ __('rates.alert_cta') }}</span>
                </a>

                <x-info-popover :label="__('rates.alert_cta')">
                    {{ __('rates.alert_hint') }}
                </x-info-popover>
            </div>
        </div>

        {{--
            Currency is the only question here with no sensible default, so it
            is the only one asked on sight. Everything else - market, kind of
            transaction, city, and the calculator - has an answer already and
            waits behind a control, because a page called "All Exchange Rates"
            that shows no rates until you scroll is answering the wrong
            question first.

            Currency is a set of links rather than a field: picking one
            navigates, carrying the current amount and intent.
        --}}
        <div class="mt-8">
            <span class="{{ $labelClass }}">{{ __('rates.currency_label') }}</span>
            {{-- Eleven currencies wrap to six rows on a phone, so on small
            viewports they scroll sideways instead. --}}
            <div class="mt-2 flex gap-2 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] sm:flex-wrap sm:overflow-visible [&::-webkit-scrollbar]:hidden">
                @foreach ($currencies as $currency)
                    <a
                        href="{{ $link(['currency' => $currency->code]) }}"
                        class="inline-flex shrink-0 items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold tracking-wide uppercase transition {{ $selectedCurrency?->id === $currency->id ? 'border-primary/50 bg-primary/20 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                    >
                        <span aria-hidden="true" class="text-base">{{ \App\Models\Currency::flag($currency->code) }}</span>
                        {{ $currency->code }}
                    </a>
                @endforeach
            </div>

        </div>

        {{--
            Collapsed by default, and open whenever an amount is already in the
            URL. Most people arrive to read rates; the ones who came to work out
            a transaction take one click to say so, and get the whole panel.
        --}}
        <div x-data="{ open: @js($calculating) }" class="mt-4">
            <button
                type="button"
                x-show="!open"
                @click="open = true"
                class="flex w-full items-center justify-between gap-3 rounded-xl border border-placeholder bg-white px-5 py-4 text-start transition hover:border-border-muted"
            >
                <span class="flex min-w-0 items-center gap-3">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0 text-accent-yellow" aria-hidden="true">
                        <rect width="16" height="20" x="4" y="2" rx="2" />
                        <line x1="8" x2="16" y1="6" y2="6" />
                        <line x1="8" x2="8" y1="14" y2="14" />
                        <line x1="12" x2="12" y1="14" y2="14" />
                        <line x1="16" x2="16" y1="14" y2="14" />
                        <line x1="8" x2="8" y1="18" y2="18" />
                        <line x1="12" x2="12" y1="18" y2="18" />
                        <line x1="16" x2="16" y1="18" y2="18" />
                    </svg>
                    <span class="min-w-0 text-sm font-medium break-words text-ink">{{ __('rates.calculator_prompt') }}</span>
                </span>
                <span aria-hidden="true" class="shrink-0 text-muted">&rsaquo;</span>
            </button>

        {{--
            The calculator, which is a second question rather than the point of
            the page - so it sits in its own panel and produces a different
            table when answered.

            Alpine-only, deliberately: with JS off the buy/sell radios do not
            self-submit, so the button is the only way to apply an intent change
            and must stay enabled.
        --}}
        <form
            method="GET" action="{{ route('rates.index') }}"
            x-show="open" x-cloak
            x-data="{ amount: @js($amount ?? ''), intent: @js($intent) }"
            class="rounded-2xl border border-placeholder bg-white p-6 sm:p-8"
        >
            @foreach (['type', 'org_type', 'organization', 'city', 'lat', 'lng'] as $carried)
                @if (!empty($baseParams[$carried]))
                    <input type="hidden" name="{{ $carried }}" value="{{ $baseParams[$carried] }}">
                @endif
            @endforeach
            <input type="hidden" name="currency" value="{{ $selectedCurrency?->code }}">

            {{--
                "I have 5,000 USD, I want AMD" rather than "Buy USD / Sell USD".
                Buy and sell are written from the institution's side - the column
                headed Sell is the one a buyer pays - and every visitor had to
                decode that before the calculator meant anything.

                The pair is fully determined: there are only two currencies in
                play, so choosing what you have decides what you want. "I want"
                is therefore stated, not asked.
            --}}
            <div class="grid grid-cols-1 items-end gap-x-6 gap-y-5 md:grid-cols-12">
                <div class="min-w-0 md:col-span-6">
                    <label for="amount" class="{{ $labelClass }}">{{ __('rates.i_have') }}</label>
                    <div class="mt-2 flex min-w-0 gap-2">
                        <input
                            type="number" inputmode="decimal" step="0.01" min="0"
                            name="amount" id="amount"
                            x-model="amount"
                            value="{{ $amount }}"
                            placeholder="{{ __('rates.amount_placeholder') }}"
                            class="w-full min-w-0 rounded-lg border border-border-muted bg-white px-4 py-2.5 text-base text-ink focus:border-primary focus:outline-none"
                        >
                        {{-- Still named "intent", still buy/sell on the wire.
                        Only the label changed: the value is now the currency the
                        visitor is handing over. --}}
                        <label for="intent" class="sr-only">{{ __('rates.i_have') }}</label>
                        <select
                            name="intent" id="intent"
                            x-model="intent"
                            onchange="if (Number(this.form.amount.value) > 0) { this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit(); }"
                            class="shrink-0 rounded-lg border border-border-muted bg-white px-3 py-2.5 text-base font-semibold text-ink focus:border-primary focus:outline-none"
                        >
                            <option value="sell" @selected(! $isBuying)>{{ $currencyCode }}</option>
                            <option value="buy" @selected($isBuying)>{{ $amdCode }}</option>
                        </select>
                    </div>
                </div>

                <div class="min-w-0 md:col-span-3">
                    <span class="{{ $labelClass }}">{{ __('rates.i_want') }}</span>
                    {{-- A field-shaped statement, not a control: with two
                    currencies there is nothing here to choose. --}}
                    <p
                        class="mt-2 rounded-lg border border-placeholder bg-placeholder/25 px-4 py-2.5 text-base font-semibold break-words text-ink"
                        x-text="intent === 'buy' ? @js($currencyCode) : @js($amdCode)"
                    >{{ $targetCode }}</p>
                </div>

                {{-- Number(), not a truthiness check: the string "0" is truthy
                in JS, and the controller drops a zero amount anyway. --}}
                <button
                    type="submit"
                    :disabled="!(Number(amount) > 0)"
                    class="w-full rounded-lg bg-primary px-6 py-3 text-sm font-semibold break-words text-white transition hover:bg-primary-dark disabled:cursor-not-allowed disabled:bg-placeholder disabled:text-muted disabled:hover:bg-placeholder md:col-span-3"
                >
                    {{ __('rates.calculate_submit') }}
                </button>
            </div>

            {{-- Round numbers people actually type, scaled to whatever they are
            handing over: 100 dram is not a transaction, and 500,000 US dollars
            is not one either. Saves the keyboard entirely on a phone and
            submits in the same tap. --}}
            <div class="mt-5 flex flex-wrap items-center gap-2">
                <span class="text-xs text-muted">{{ __('rates.quick_amounts') }}</span>
                @foreach (($isBuying ? [50000, 100000, 500000, 1000000] : [100, 500, 1000, 5000]) as $quick)
                    {{-- Links, not submit buttons: a submit button named
                    "amount" would serialize alongside the field of the same
                    name and leave which one wins to browser ordering. A link is
                    also shareable and works with JS off. --}}
                    <a
                        href="{{ $link(['amount' => $quick]) }}"
                        class="rounded-full border px-3 py-1 text-xs font-medium transition {{ (int) $amount === $quick ? 'border-primary/50 bg-primary/20 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                    >
                        {{ number_format($quick) }}
                    </a>
                @endforeach

                @if ($calculating)
                    {{-- Not shrink-0: the Armenian string is wider than a 320px
                    screen and would push the page sideways. --}}
                    <a href="{{ $link(['amount' => null]) }}" class="min-w-0 text-xs break-words text-muted underline hover:text-ink">
                        {{ __('rates.calculator_clear') }}
                    </a>
                @endif
            </div>

            {{-- The sentence version of the two fields above, so what was
            selected is legible without reading a form back. --}}
            <p class="mt-4 text-sm break-words text-muted">
                {{ __('rates.direction_summary', [
                    'amount' => $calculating ? number_format($amount, $amount < 1000 ? 2 : 0) : __('rates.amount_placeholder'),
                    'from' => $sourceCode,
                    'to' => $targetCode,
                ]) }}
            </p>
        </form>
        </div>

        {{--
            The state in words, the controls behind a button. Hiding filters
            usually breaks because a narrowed table then looks like the full
            one; saying "Cash rates · banks · Gyumri" out loud fixes that
            without keeping thirteen pills on screen.

            Alpine only decides whether the panel is open. Every control inside
            is still a link or a GET form, so with JS off the panel renders
            open and the page works exactly as before.
        --}}
        <div x-data="{ open: window.__ratesFiltersOpen ?? false }" x-effect="window.__ratesFiltersOpen = open" class="mt-6">
            <button
                type="button"
                @click="open = !open"
                :aria-expanded="open"
                class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition {{ $activeFilterCount ? 'border-primary/50 bg-primary/20 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                    <path d="M3 6h18M7 12h10M11 18h2" />
                </svg>
                {{ __('rates.more_filters') }}@if ($activeFilterCount) ({{ $activeFilterCount }})@endif
            </button>

        <div x-show="open" x-cloak class="mt-5 flex flex-wrap items-start gap-x-10 gap-y-5 rounded-xl border border-placeholder bg-white px-5 py-5">

        {{-- Market tabs. Banks and exchange offices quote very different
        levels, so they are separated rather than interleaved. --}}
        @if ($orgTypes->count() > 1)
            <div>
                <span class="{{ $labelClass }}">{{ __('rates.market_label') }}</span>
                <div class="mt-2 flex flex-wrap gap-2">
                <a
                    href="{{ $link(['org_type' => null, 'organization' => null]) }}"
                    class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $selectedOrgType === null ? 'border-primary/50 bg-primary/20 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                >
                    {{ __('rates.market_all') }}
                </a>
                @foreach ($orgTypes as $orgType)
                    <a
                        href="{{ $link(['org_type' => $orgType, 'organization' => null]) }}"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $selectedOrgType === $orgType ? 'border-primary/50 bg-primary/20 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                    >
                        {{ __('rates.markets.' . $orgType) }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Transaction type: only the ones this currency actually has, so a
        pill never leads to an empty table. --}}
        <div>
            <span class="{{ $labelClass }}">{{ __('rates.type_label') }}</span>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($availableTypes as $typeValue)
                    <a
                        href="{{ $link(['type' => $typeValue]) }}"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition {{ $selectedType->value === $typeValue ? 'border-primary/50 bg-primary/20 text-ink' : 'border-placeholder bg-white text-muted hover:text-ink' }}"
                    >
                        {{ __('organizations.rate_types.' . $typeValue) }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- The organization and city selects. Their own mobile-only collapse
        is gone: the whole panel is behind one button now, at every width. --}}
        <div>
            <div class="flex flex-wrap items-end gap-x-3 gap-y-3">
                <form method="GET" action="{{ route('rates.index') }}" class="contents">
                    <input type="hidden" name="currency" value="{{ $selectedCurrency?->code }}">
                    <input type="hidden" name="type" value="{{ $selectedType->value }}">
                    <input type="hidden" name="org_type" value="{{ $selectedOrgType }}">
                    <input type="hidden" name="intent" value="{{ $intent }}">
                    {{-- lat/lng were previously omitted here, so changing bank or
                    city silently dropped an active "find nearby". --}}
                    <input type="hidden" name="amount" value="{{ $amount }}">
                    @if ($hasLocation)
                        <input type="hidden" name="lat" value="{{ $latitude }}">
                        <input type="hidden" name="lng" value="{{ $longitude }}">
                    @endif

                    {{-- Only once a market is chosen. Under "All" the list mixes
                    banks and exchange offices, and no single label is honest about
                    what it contains. --}}
                    @if ($selectedOrgType !== null)
                        <div>
                            <label for="filter-organization" class="{{ $labelClass }}">
                                {{ __('rates.filter_org.'.$selectedOrgType) }}
                            </label>
                            <select
                                name="organization"
                                id="filter-organization"
                                onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                                class="mt-2 rounded-full border border-placeholder bg-white px-4 py-2 text-sm font-medium text-ink focus:border-primary focus:outline-none"
                            >
                                <option value="">{{ __('rates.filter_org_all.'.$selectedOrgType) }}</option>
                                @foreach ($organizations as $organization)
                                    <option value="{{ $organization->slug }}" @selected($selectedOrganization?->id === $organization->id)>
                                        {{ $organization->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($cities->isNotEmpty())
                        <div>
                            <label for="filter-city" class="{{ $labelClass }}">{{ __('rates.filter_city') }}</label>
                            <select
                                name="city"
                                id="filter-city"
                                onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                                title="{{ __('rates.filter_city_hint') }}"
                                class="mt-2 rounded-full border border-placeholder bg-white px-4 py-2 text-sm font-medium text-ink focus:border-primary focus:outline-none"
                            >
                                <option value="">{{ __('rates.filter_city_all') }}</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city }}" @selected($selectedCity === $city)>{{ $city }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </form>

                {{--
                    Client-side only - the server can't know the visitor's
                    coordinates until the browser's own prompt hands them over.
                    Once granted, reloads the current URL with lat/lng merged
                    in, keeping every other filter intact.
                --}}
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
                            href="{{ $link(['lat' => null, 'lng' => null, 'sort' => null, 'direction' => null]) }}"
                            class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-medium text-primary"
                        >
                            📍 {{ __('rates.nearby_active') }}
                            <span aria-hidden="true">&times;</span>
                        </a>
                    @else
                        <button
                            type="button"
                            @click="findNearby()"
                            :disabled="state === 'locating'"
                            class="inline-flex items-center gap-1 rounded-full border border-placeholder bg-white px-4 py-2 text-sm font-medium text-ink hover:border-primary disabled:opacity-60"
                        >
                            <span x-show="state !== 'locating'">📍 {{ __('rates.find_nearby') }}</span>
                            <span x-show="state === 'locating'" x-cloak>{{ __('rates.locating') }}</span>
                        </button>
                        <p x-show="state === 'error'" x-cloak class="mt-1 text-xs text-red-600">{{ __('rates.location_error') }}</p>
                    @endif
                </div>

            </div>
        </div>

        </div>
        </div>

        @if ($centralBankRate)
            {{-- Stated once, as a reference. It used to sit in the transaction
            type row as a peer of Cash and Card, which sent visitors to rows
            they could not act on. --}}
            <p class="mt-5 text-sm break-words text-muted">
                {{ __('rates.central_bank_reference', [
                    'rate' => number_format((float) $centralBankRate['rate'], 2),
                    'code' => $selectedCurrency?->code,
                ]) }}
            </p>
        @endif

        @php
            // Ascending first, flipping on a repeat click of the same column.
            $sortLink = fn (string $column) => $link([
                'sort' => $column,
                'direction' => $sort === $column && $direction === 'asc' ? 'desc' : 'asc',
            ]);
            $sortArrow = fn (string $column) => $sort === $column ? ($direction === 'asc' ? ' ▲' : ' ▼') : '';

            // Without an amount there is no single winner - buy and sell rank
            // in opposite directions - so each column names its own. The
            // organization buys low and sells high, which for the visitor is
            // the highest buy and the lowest sell.
            $bestBuy = collect($ranked['rows'])->max(fn ($row) => (float) $row->buy_rate);
            $bestSell = collect($ranked['rows'])->min(fn ($row) => (float) $row->sell_rate);
            $isBestRate = fn (float $value, ?float $target) => $target !== null && abs($value - $target) < 0.00005;

            $totalColumn = __('rates.you_receive_column');

            // Who is quoting each winning figure. Ties are the norm - six banks
            // at 367.00 - so the card names the first and counts the rest,
            // exactly as the best-rate band does.
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

            // The midpoint of each row averaged across the list. Stated as a
            // reference, not a rate: with banks near 365 and exchange offices
            // near 385 the mean sits in a gap nobody quotes.
            $marketAverage = collect($ranked['rows'])
                ->avg(fn ($row) => ((float) $row->buy_rate + (float) $row->sell_rate) / 2);

            $organizationCount = collect($ranked['rows'])->pluck('organization_id')->unique()->count();

            // Green buy, red sell - the pairing every currency board in the
            // country uses, so it is the one visitors arrive already able to
            // read. The average stays ink: it is a reference, not an offer.
            $summaryCards = [
                [
                    'label' => __('rates.summary_best_buy'),
                    'value' => $bestBuy,
                    'note' => $holderOf(fn ($row) => $isBestRate((float) $row->buy_rate, $bestBuy)),
                    'hint' => __('rates.buy_hint'),
                    'tone' => 'text-primary',
                ],
                [
                    'label' => __('rates.summary_best_sell'),
                    'value' => $bestSell,
                    'note' => $holderOf(fn ($row) => $isBestRate((float) $row->sell_rate, $bestSell)),
                    'hint' => __('rates.sell_hint'),
                    'tone' => 'text-accent-red',
                ],
                [
                    'label' => __('rates.summary_average'),
                    'value' => $marketAverage,
                    'note' => trans_choice('rates.summary_across', $organizationCount, ['count' => $organizationCount]),
                    'hint' => __('rates.summary_average_hint'),
                    'tone' => 'text-ink',
                ],
            ];
        @endphp

        @if ($rowCount > 0)
            {{--
                Three figures a visitor would otherwise get by reading fourteen
                rows twice - and they stay put whether or not an amount has been
                entered. Swapping them out for the best-rate band the moment a
                number was typed was the single biggest reason this page felt
                like it had jumped to a different screen.
            --}}
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    @foreach ($summaryCards as $card)
                        <div class="min-w-0 rounded-xl border border-placeholder bg-white p-4">
                            <span class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-muted uppercase">
                                <span class="min-w-0 break-words">{{ $card['label'] }}</span>
                                <x-info-popover :label="$card['label']">{{ $card['hint'] }}</x-info-popover>
                            </span>

                            {{-- Scaled down below sm: at 36px a five-figure
                            rate plus its unit is wider than a 320px screen, and
                            it is set nowrap so it would push the page rather
                            than break. --}}
                            <p class="mt-1 flex items-end gap-2 whitespace-nowrap">
                                <span class="text-3xl font-semibold tracking-tight tabular-nums sm:text-4xl {{ $card['tone'] }}">
                                    {{ number_format((float) $card['value'], 2) }}
                                </span>
                                <span class="pb-1.5 text-sm text-muted">{{ __('exchange_quotes.request.amd') }}</span>
                            </p>

                            <p class="mt-1 truncate text-sm text-muted" title="{{ $card['note'] }}">{{ $card['note'] }}</p>
                        </div>
                    @endforeach
                </div>

            {{--
                The answer, before the table, and filled rather than tinted -
                a pale strip the width of the page reads as a caption on the
                table instead of the thing the visitor came for.

                Only while calculating: with no amount there is no single
                winner to state, because buy and sell rank in opposite
                directions.
            --}}
            @if ($calculating && $best)
                {{-- A pale card with one saturated edge, rather than a block of
                solid green: filled, it was the loudest thing on a page whose
                point is the table under it, and it left the total set in white
                on green where every other number here is ink on white. --}}
                <div class="relative mt-8 flex flex-wrap items-center justify-between gap-x-6 gap-y-4 overflow-hidden rounded-2xl border-2 border-primary/40 bg-primary/5 py-5 pr-4 pl-6 sm:pr-6 sm:pl-8">
                    <span class="absolute inset-y-0 left-0 w-2 bg-primary" aria-hidden="true"></span>

                    <div class="flex min-w-0 items-center gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-placeholder bg-white shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-6 w-6 fill-accent-yellow" aria-hidden="true">
                                <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            {{-- "Current" next to "1 day ago" is a contradiction
                            the visitor has to resolve, and they resolve it by
                            trusting the page less. Claim only what is true. --}}
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
                        {{-- Scaled down on the narrowest screens: at 36px a
                        six-figure total plus "драм" is wider than 320px, and it
                        is set nowrap so it would push the page rather than
                        break. --}}
                        <p class="mt-1 text-2xl font-bold tracking-tight whitespace-nowrap text-ink tabular-nums sm:text-4xl">
                            {{ $amd($convert((float) $best->{$rateField})) }}
                            <span class="text-base font-normal text-muted sm:text-xl">{{ $targetCode }}</span>
                        </p>
                    </div>
                </div>
            @endif

            {{--
                One thin line, not a heading block. The page title already says
                what this is and the summary above says how it is narrowed, so a
                second "All rates" heading was restating both.
            --}}
            <div class="mt-6 flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2">
                <p class="min-w-0 text-sm break-words text-muted">
                    {{ trans_choice('rates.results_count', $rowCount, ['count' => $rowCount]) }}
                    @if ($hasNonDefaultFilter)
                        &middot; <a href="{{ route('rates.index', array_filter(['currency' => $selectedCurrency?->code])) }}" class="underline hover:text-ink">{{ __('rates.reset_filters') }}</a>
                    @endif
                    {{-- 0.01, not 1: the floor was written for dram totals in
                    the thousands, and silently swallowed the whole line for a
                    small amount of a low-value currency. --}}
                    @if ($marketSaving !== null && $marketSaving >= 0.01)
                        {{-- "difference between best and worst" is a fact about
                        the table; "you keep X by picking the best" is a fact
                        about the visitor. Same number. --}}
                        &middot; {{ __('rates.market_saving_sell', [
                            'amount' => $amd($marketSaving),
                            'currency' => $targetCode,
                        ]) }}
                    @endif
                    @if ($allStale)
                        &middot; <span class="text-[#B4791F]">{{ __('rates.all_stale_notice') }}</span>
                    @endif
                </p>

                {{-- Only offered while calculating: that table shows one rate
                named from the visitor's side, because reading the pair
                correctly means knowing that "Buy" is the rate the bank buys at,
                not the one you buy at. The plain table is the pair.

                A two-state control rather than the sentence that used to sit
                here: it says which of the two views you are in, which one link
                naming only the other view never did. Still two links, so it
                works with JS off and stays shareable. --}}
                {{-- Always offered, and it changes exactly one thing: whether
                the table carries the spread column. It used to appear only while
                calculating, and to swap the whole rate pair in and out. --}}
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <span class="{{ $labelClass }}">{{ __('rates.view_label') }}</span>
                        <div class="flex rounded-lg border border-placeholder bg-placeholder/25 p-1">
                            @foreach (['view_simple' => false, 'view_detailed' => true] as $key => $both)
                                @php $isCurrent = $detailed === $both; @endphp
                                <a
                                    href="{{ $link(['both' => $both ? 1 : null]) }}"
                                    aria-current="{{ $isCurrent ? 'true' : 'false' }}"
                                    class="min-w-0 rounded-md px-3 py-1 text-xs font-medium break-words transition {{ $isCurrent ? 'bg-primary text-white shadow-sm' : 'text-muted hover:text-ink' }}"
                                >
                                    {{ __('rates.'.$key) }}
                                </a>
                            @endforeach
                        </div>
                </div>
            </div>

            {{-- Mobile: a row list. A table has no room for a readable name
            once the rate and the total both need space. --}}
            <div class="mt-4 overflow-hidden rounded-xl border border-placeholder sm:hidden">
                @foreach ($ranked['rows'] as $rate)
                    @php $total = $calculating ? $convert((float) $rate->{$rateField}) : null; @endphp
                    {{-- The name is the link, not the whole card: the meta line
                    under it now carries a Directions link of its own, and an
                    anchor inside an anchor is invalid - browsers close the outer
                    one early and the row falls apart. --}}
                    <div class="flex items-center gap-3 border-b border-placeholder px-4 py-4 last:border-b-0">
                        <a href="{{ $rate->organization_url }}" class="shrink-0">
                            <x-rates.org-mark :logo="$rate->organization_logo" :name="$rate->organization_name" />
                        </a>

                        <div class="min-w-0 flex-1">
                            {{-- No star beside the name: the total carries it,
                            so each row is marked once, on the number it is
                            about. --}}
                            <a href="{{ $rate->organization_url }}" class="block font-medium break-words text-ink hover:text-primary">{{ $rate->organization_name }}</a>
                            <x-rates.org-meta
                                :market="$showMarket ? __('rates.market_badge.' . $rate->organization_type) : null"
                                :scraped-at="$rate->scraped_at"
                                :stale="$isStale($rate->scraped_at)"
                                :distance="$hasLocation && isset($rate->distance_km) ? __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) : null"
                                :directions="$rate->branch ?? null"
                            />
                            @if ($rate->organization_reviews_count > 0)
                                <span class="mt-1 flex items-center gap-1">
                                    <x-star-rating :rating="$rate->organization_reviews_avg_rating" size="h-3 w-3" />
                                    <span class="text-xs text-muted">({{ $rate->organization_reviews_count }})</span>
                                </span>
                            @endif
                        </div>

                        {{-- The same three figures the table carries, stacked:
                        the pair stays put and the total joins it, so a phone
                        sees the same change a desktop does. --}}
                        <div class="shrink-0 text-end">
                            <p class="whitespace-nowrap tabular-nums">
                                <span class="font-bold text-ink">{{ number_format($rate->buy_rate, 2) }}</span>
                                <span class="text-muted" aria-hidden="true"> / </span>
                                <span class="font-bold text-accent-red">{{ number_format($rate->sell_rate, 2) }}</span>
                            </p>
                            @if ($calculating)
                                <p class="mt-0.5 flex items-center justify-end gap-1.5 whitespace-nowrap tabular-nums">
                                    @if ($rate->rank === 1)
                                        <x-rates.best-chip />
                                    @endif
                                    <span class="font-bold text-ink">{{ $amd($total) }}</span>
                                    <span class="text-xs font-normal text-muted">{{ $targetCode }}</span>
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Desktop table. Three columns: who, the rate, the total. The
            market and the timestamp ride under the name rather than claiming
            columns of their own - they qualify the row, they are not something
            you compare across rows. --}}
            {{-- relative, so the sr-only labels inside resolve against this box: with
            no positioned ancestor they fall back to the page, and an absolutely
            positioned span sitting at x=892 in a scrolled-out column drags the
            whole document sideways. --}}
            <div class="relative mt-4 hidden overflow-x-auto rounded-xl border border-placeholder sm:block">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        {{-- Tinted, so the column names read as the frame around
                        the numbers rather than as a first row of them. --}}
                        <tr class="border-b border-placeholder bg-placeholder/25 text-xs font-semibold tracking-wider text-muted uppercase">
                            <th class="px-6 py-3 text-left">{{ __('rates.provider_column') }}</th>
                            {{-- One table, whatever the visitor has typed. The
                            rate pair is always here, always in the same two
                            places; an amount adds a column, it does not swap the
                            table for a different one. --}}
                            <th class="px-6 py-3 text-right">
                                <a href="{{ $sortLink('buy_rate') }}" class="hover:text-ink" title="{{ __('rates.buy_hint') }}">{{ __('rates.buy_column') }}{{ $sortArrow('buy_rate') }}</a>
                            </th>
                            <th class="px-6 py-3 text-right">
                                <a href="{{ $sortLink('sell_rate') }}" class="hover:text-ink" title="{{ __('rates.sell_hint') }}">{{ __('rates.sell_column') }}{{ $sortArrow('sell_rate') }}</a>
                            </th>

                            {{-- Detailed only: the gap between the two columns
                            printed either side of it. --}}
                            @if ($detailed)
                                <th class="hidden px-4 py-3 text-right lg:table-cell" title="{{ __('rates.spread_hint') }}">
                                    {{ __('rates.spread_column') }}
                                </th>
                            @endif

                            @if ($calculating)
                                {{-- Tinted, because this is the number the
                                calculation exists to produce and it sits
                                furthest from the name. --}}
                                <th class="bg-placeholder/25 px-6 py-3 text-right">
                                    <span class="inline-flex items-center gap-1.5">
                                        <a href="{{ $sortLink($rateField) }}" class="hover:text-ink">{{ $totalColumn }}{{ $sortArrow($rateField) }}</a>
                                        <x-info-popover :label="$totalColumn">
                                            {{ __($isBuying ? 'rates.rate_column_hint_buy' : 'rates.rate_column_hint_sell', ['code' => $selectedCurrency?->code]) }}
                                        </x-info-popover>
                                    </span>
                                </th>
                            @endif

                            {{-- Out of the line under the name and into a column
                            of its own once the width allows one, so freshness
                            can be compared down the list rather than read row by
                            row. --}}
                            <th class="hidden px-4 py-3 text-right md:table-cell">{{ __('rates.updated_column') }}</th>

                            {{-- Unlabelled: the pin says it, and a heading over
                            a 40px button would be wider than the column. --}}
                            <th class="px-4 py-3"><span class="sr-only">{{ __('rates.directions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ranked['rows'] as $rate)
                            @php $total = $calculating ? $convert((float) $rate->{$rateField}) : null; @endphp
                            <tr class="border-b border-placeholder last:border-b-0 hover:bg-placeholder/15">
                                <td class="px-6 py-4">
                                    {{-- See the mobile card: the meta line holds
                                    a link now, so the cell cannot be one. --}}
                                    <div class="flex items-center gap-3">
                                        <a href="{{ $rate->organization_url }}" class="shrink-0">
                                            <x-rates.org-mark :logo="$rate->organization_logo" :name="$rate->organization_name" />
                                        </a>
                                        <div class="min-w-0">
                                            {{-- No star here: with an amount the
                                            total column marks the winner, and
                                            without one each rate column marks
                                            its own. One star per row, always on
                                            the number it is about. --}}
                                            <a href="{{ $rate->organization_url }}" class="block truncate font-medium text-ink hover:text-primary">{{ $rate->organization_name }}</a>
                                            {{-- No directions here: the row
                                            ends with a button for that. The
                                            timestamp hides itself once its own
                                            column appears. --}}
                                            <x-rates.org-meta
                                                :market="$showMarket ? __('rates.market_badge.' . $rate->organization_type) : null"
                                                :scraped-at="$rate->scraped_at"
                                                :stale="$isStale($rate->scraped_at)"
                                                :distance="$hasLocation && isset($rate->distance_km) ? __('rates.distance_km', ['km' => number_format($rate->distance_km, 1)]) : null"
                                                timestamp-class="md:hidden"
                                            />
                                            @if ($rate->organization_reviews_count > 0)
                                                <span class="mt-1 flex items-center gap-1">
                                                    <x-star-rating :rating="$rate->organization_reviews_avg_rating" size="h-3 w-3" />
                                                    <span class="text-xs text-muted">({{ $rate->organization_reviews_count }})</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                @php
                                    // With an amount on screen the answer is the
                                    // total, so that is the column that carries
                                    // the star. Without one there is no single
                                    // winner - buy and sell rank in opposite
                                    // directions - so each column marks its own.
                                    $winsBuy = ! $calculating && $isBestRate((float) $rate->buy_rate, $bestBuy);
                                    $winsSell = ! $calculating && $isBestRate((float) $rate->sell_rate, $bestSell);
                                    $winsTotal = $calculating && $rate->rank === 1;
                                @endphp

                                {{-- The winning figure is bolder as well as starred. --}}
                                <td @class(['px-6 py-4 text-right text-base text-ink tabular-nums', 'font-semibold' => $winsBuy, 'font-medium' => ! $winsBuy])>
                                    <span class="inline-flex items-center justify-end gap-2">
                                        @if ($winsBuy)
                                            <x-rates.best-chip />
                                        @endif
                                        {{ number_format($rate->buy_rate, 2) }}
                                    </span>
                                </td>
                                {{-- The same red as the sell-side summary card, so buy and
                                sell read the same way in both places. --}}
                                <td @class(['px-6 py-4 text-right text-base tabular-nums text-accent-red', 'font-semibold' => $winsSell, 'font-medium' => ! $winsSell])>
                                    <span class="inline-flex items-center justify-end gap-2">
                                        @if ($winsSell)
                                            <x-rates.best-chip />
                                        @endif
                                        {{ number_format($rate->sell_rate, 2) }}
                                    </span>
                                </td>

                                @if ($detailed)
                                    <td class="hidden px-4 py-4 text-right text-muted tabular-nums lg:table-cell">
                                        {{ number_format((float) $rate->sell_rate - (float) $rate->buy_rate, 2) }}
                                    </td>
                                @endif

                                @if ($calculating)
                                    <td @class(['bg-placeholder/25 px-6 py-4 text-right text-base whitespace-nowrap text-ink tabular-nums', 'font-bold' => $winsTotal, 'font-medium' => ! $winsTotal])>
                                        <span class="inline-flex items-center justify-end gap-2">
                                            @if ($winsTotal)
                                                <x-rates.best-chip />
                                            @endif
                                            <span>
                                                {{ $amd($total) }}
                                                <span class="text-xs font-normal text-muted">{{ $targetCode }}</span>
                                            </span>
                                        </span>
                                    </td>
                                @endif

                                <td class="hidden px-4 py-4 text-right text-xs whitespace-nowrap md:table-cell">
                                    @if ($rate->scraped_at)
                                        <span @class(['text-[#B4791F]' => $isStale($rate->scraped_at), 'text-muted' => ! $isStale($rate->scraped_at)])>
                                            {{ Carbon::parse($rate->scraped_at)->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-muted" aria-hidden="true">&mdash;</span>
                                    @endif
                                </td>

                                {{-- Nobody comes here to read a rate; they come
                                to go and exchange money. This is the last step
                                of that, so it is a real link out to whatever maps
                                app the device has, not another page of ours. --}}
                                <td class="px-4 py-4">
                                    @if ($rate->branch ?? null)
                                        <a
                                            href="{{ $rate->branch['url'] }}"
                                            target="_blank" rel="noopener noreferrer"
                                            title="{{ $rate->branch['address'] ?: $rate->branch['name'] }}"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full text-primary transition hover:bg-primary hover:text-white"
                                        >
                                            <span class="sr-only">{{ __('rates.directions') }}</span>
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                                                <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
                                                <circle cx="12" cy="10" r="3" />
                                            </svg>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            {{-- Never a dead end: offer the nearest combination that has data
            rather than only reporting the absence. --}}
            <div class="mt-8 rounded-2xl border border-dashed border-placeholder px-6 py-16 text-center">
                <p class="text-sm text-muted">{{ __('rates.no_rates_match') }}</p>

                <div class="mt-5 flex flex-wrap items-center justify-center gap-3">
                    @if ($suggestedType)
                        <a href="{{ $link(['type' => $suggestedType]) }}" class="rounded-md bg-primary px-5 py-2.5 text-sm font-medium text-white transition hover:bg-primary-dark">
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


    </section>

    {{-- Outside the panel: it is morphed on every filter click, and a dialog
    patched underneath an open form would lose what was typed into it. --}}
    <x-rate-alert-modal
        :currencies="$currencies"
        :organizations="$alertOrganizations"
        :rate-types="$alertRateTypes"
    />
    {{--
        Filtering used to reload the whole document - re-parsing every asset,
        losing scroll position and flashing the header on each pill. This swaps
        only #rates-panel from the same URL the link already points at, so the
        page stays put and the address bar still holds shareable state.

        Progressive enhancement on purpose: every control here is a real link or
        a GET form, so with JS off, or if any fetch fails, the browser just
        navigates as before. Nothing depends on this script to work.
    --}}
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

                // Morph can land the patched control on a fresh node, which
                // drops focus to <body> - so a keyboard user who picks a city
                // loses their place. Remembered by id and restored after.
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

                    // Morph, not innerHTML: patching only the nodes that
                    // actually differ means the untouched parts of the page
                    // never repaint, so there is no flash and no scroll jump.
                    // Replacing the subtree also tore down every Alpine
                    // component inside it on each click.
                    window.Alpine.morph(panel, next.outerHTML);

                    if (focusedId) {
                        document.getElementById(focusedId)?.focus({ preventScroll: true });
                    }

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

            // Only links that stay on this page. Organization rows, the alert
            // and the negotiate CTA all point elsewhere and navigate normally.
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
