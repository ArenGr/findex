@php
    $tabs = [
        'credit-cards' => __('offers.tabs.credit_cards'),
        'insurance' => __('offers.tabs.insurance'),
        'mortgages' => __('offers.tabs.mortgages'),
        'personal-loans' => __('offers.tabs.personal_loans'),
        'business-loans' => __('offers.tabs.business_loans'),
        'banking' => __('offers.tabs.banking'),
        'investing' => __('offers.tabs.investing'),
        'student-loans' => __('offers.tabs.student_loans'),
    ];

    $rows = array_fill(0, 4, [
        'apr' => '6.28%',
        'interest_rate' => '6.13%',
        'est_payment' => '$2431.0 / ' . __('offers.per_month'),
        'total_fees' => '$6324.0',
        'apr_secondary' => '6.28%',
    ]);
@endphp

<section
    id="offers"
    x-data="{
        tab: (() => {
            const requested = new URLSearchParams(window.location.search).get('tab');
            const valid = @js(array_keys($tabs));
            return valid.includes(requested) ? requested : 'credit-cards';
        })(),
    }"
    class="mx-auto max-w-7xl px-6 py-16 lg:px-10 scroll-mt-24"
>
    <h2 class="font-heading text-2xl font-bold text-ink lg:text-3xl">
        {{ __('offers.heading', ['date' => now()->translatedFormat('Y F')]) }}
    </h2>

    {{-- Tabs --}}
    <div class="mt-8 flex gap-1 overflow-x-auto border-b border-placeholder">
        @foreach ($tabs as $key => $label)
            <button
                type="button"
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'bg-primary text-white' : 'text-muted hover:text-ink'"
                class="shrink-0 px-4 py-3 text-xs font-semibold tracking-wide whitespace-nowrap uppercase transition"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Credit cards content --}}
    <div x-show="tab === 'credit-cards'" class="overflow-hidden border border-t-0 border-placeholder">
        @foreach ($rows as $row)
            <div class="grid grid-cols-2 items-center gap-4 border-b border-placeholder px-6 py-6 last:border-b-0 sm:grid-cols-4 lg:grid-cols-7">
                <div class="col-span-2 sm:col-span-1">
                    <div class="h-14 w-24 rounded bg-placeholder"></div>
                    <a href="#" class="mt-2 inline-block text-xs text-muted hover:text-primary">{{ __('common.more') }}</a>
                </div>

                <div>
                    <p class="text-xs text-subtle uppercase">{{ __('offers.columns.apr') }}</p>
                    <p class="mt-1 font-semibold text-ink">{{ $row['apr'] }}</p>
                </div>

                <div>
                    <p class="text-xs text-subtle uppercase">{{ __('offers.columns.interest_rate') }}</p>
                    <p class="mt-1 font-semibold text-ink">{{ $row['interest_rate'] }}</p>
                </div>

                <div>
                    <p class="text-xs text-subtle uppercase">{{ __('offers.columns.est_payment') }}</p>
                    <p class="mt-1 font-semibold text-ink">{{ $row['est_payment'] }}</p>
                </div>

                <div>
                    <p class="text-xs text-subtle uppercase">{{ __('offers.columns.total_fees') }}</p>
                    <p class="mt-1 font-semibold text-ink">{{ $row['total_fees'] }}</p>
                </div>

                <div>
                    <p class="text-xs text-subtle uppercase">{{ __('offers.columns.apr') }}</p>
                    <p class="mt-1 font-semibold text-ink">{{ $row['apr_secondary'] }}</p>
                </div>

                <div class="col-span-2 sm:col-span-4 lg:col-span-1">
                    <a href="#" class="block bg-primary px-4 py-3 text-center text-sm font-medium text-white hover:bg-primary-dark">
                        {{ __('common.learn_more') }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Mortgages content --}}
    <div x-show="tab === 'mortgages'" x-cloak class="overflow-hidden border border-t-0 border-placeholder">
        <x-mortgage-offers-table />
    </div>

    {{-- Personal loans: standalone affordability calculator (no live per-bank
    loan offers are tracked yet, unlike mortgages). --}}
    <div x-show="tab === 'personal-loans'" x-cloak class="overflow-hidden border border-t-0 border-placeholder">
        <x-loan-affordability-calculator />
    </div>

    {{-- Banking: standalone savings/deposit growth calculator (no live
    per-bank deposit offers are tracked yet). --}}
    <div x-show="tab === 'banking'" x-cloak class="overflow-hidden border border-t-0 border-placeholder">
        <x-savings-calculator />
    </div>

    {{-- Placeholder for the other tabs - no design/content provided for these yet --}}
    @foreach (array_keys($tabs) as $key)
        @if (!in_array($key, ['credit-cards', 'mortgages', 'personal-loans', 'banking'], true))
            <div x-show="tab === '{{ $key }}'" x-cloak class="border border-t-0 border-placeholder px-6 py-16 text-center text-sm text-muted">
                {{ __('offers.coming_soon') }}
            </div>
        @endif
    @endforeach
</section>
