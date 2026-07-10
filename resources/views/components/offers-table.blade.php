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
                class="flex shrink-0 items-center gap-1.5 px-4 py-3 text-xs font-semibold tracking-wide whitespace-nowrap uppercase transition"
            >
                {{ $label }}
                @if (!in_array($key, ['mortgages', 'personal-loans', 'banking'], true))
                    <span
                        :class="tab === '{{ $key }}' ? 'bg-white/25 text-white' : 'bg-placeholder/60 text-subtle'"
                        class="rounded-full px-1.5 py-0.5 text-[9px] normal-case"
                    >
                        {{ __('offers.soon_badge') }}
                    </span>
                @endif
            </button>
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
        @if (!in_array($key, ['mortgages', 'personal-loans', 'banking'], true))
            <div
                x-show="tab === '{{ $key }}'"
                @if ($key !== 'credit-cards') x-cloak @endif
                class="border border-t-0 border-placeholder px-6 py-16 text-center text-sm text-muted"
            >
                {{ __('offers.coming_soon') }}
            </div>
        @endif
    @endforeach
</section>
