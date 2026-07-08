@php
    // Rough starting points so the calculator shows a sensible result
    // before the user changes anything.
    $defaultInitialDeposit = 500000;
    $defaultMonthlyContribution = 20000;
@endphp

<div
    x-data="{
        initialDeposit: {{ $defaultInitialDeposit }},
        monthlyContribution: {{ $defaultMonthlyContribution }},
        interestRate: 8,
        termYears: 3,

        get months() {
            return Math.max(0, this.termYears * 12);
        },

        get futureValue() {
            const r = this.interestRate / 100 / 12;
            const n = this.months;
            const fvPrincipal = this.initialDeposit * Math.pow(1 + r, n);
            const fvContributions = r === 0
                ? this.monthlyContribution * n
                : this.monthlyContribution * ((Math.pow(1 + r, n) - 1) / r);
            return fvPrincipal + fvContributions;
        },

        get totalContributed() {
            return this.initialDeposit + this.monthlyContribution * this.months;
        },

        get totalInterestEarned() {
            return Math.max(0, this.futureValue - this.totalContributed);
        },
    }"
    class="grid grid-cols-1 gap-8 px-6 py-8 lg:grid-cols-2"
>
    <div class="space-y-4">
        <label class="block">
            <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.savings_calculator.initial_deposit') }}</span>
            <input type="number" min="0" x-model.number="initialDeposit" class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.savings_calculator.monthly_contribution') }}</span>
            <input type="number" min="0" x-model.number="monthlyContribution" class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.savings_calculator.interest_rate') }} (%)</span>
            <input type="number" min="0" step="0.1" x-model.number="interestRate" class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.savings_calculator.term_years') }}</span>
            <input type="number" min="1" x-model.number="termYears" class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm">
        </label>
    </div>

    <div class="border border-placeholder bg-placeholder/10 p-6">
        <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('offers.savings_calculator.future_value') }}</p>
        <p class="mt-2 font-heading text-3xl font-bold text-primary">
            <span x-text="Math.round(futureValue).toLocaleString()"></span> ֏
        </p>

        <p class="mt-6 text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('offers.savings_calculator.total_interest_earned') }}</p>
        <p class="mt-2 font-heading text-xl font-semibold text-ink">
            <span x-text="Math.round(totalInterestEarned).toLocaleString()"></span> ֏
        </p>

        <p class="mt-6 text-xs leading-relaxed text-subtle">{{ __('offers.savings_calculator.disclaimer') }}</p>
    </div>
</div>
