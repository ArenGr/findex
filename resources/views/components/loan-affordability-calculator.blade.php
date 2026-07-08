@php
    // Rough starting points so the calculator shows a sensible result
    // before the user changes anything.
    $defaultMonthlyIncome = 300000;
@endphp

<div
    x-data="{
        monthlyIncome: {{ $defaultMonthlyIncome }},
        existingDebtPayments: 0,
        interestRate: 14,
        termMonths: 36,
        maxDtiPercent: 40,

        get maxMonthlyPayment() {
            return Math.max(0, (this.monthlyIncome * this.maxDtiPercent / 100) - this.existingDebtPayments);
        },

        get maxLoanAmount() {
            const r = this.interestRate / 100 / 12;
            const n = this.termMonths;
            if (n <= 0) return 0;
            if (r === 0) return this.maxMonthlyPayment * n;
            return this.maxMonthlyPayment * (1 - Math.pow(1 + r, -n)) / r;
        },
    }"
    class="grid grid-cols-1 gap-8 px-6 py-8 lg:grid-cols-2"
>
    <div class="space-y-4">
        <label class="block">
            <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.loan_calculator.monthly_income') }}</span>
            <input type="number" min="0" x-model.number="monthlyIncome" class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.loan_calculator.existing_debt') }}</span>
            <input type="number" min="0" x-model.number="existingDebtPayments" class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.loan_calculator.interest_rate') }} (%)</span>
            <input type="number" min="0" step="0.1" x-model.number="interestRate" class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.loan_calculator.term_months') }}</span>
            <input type="number" min="1" x-model.number="termMonths" class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm">
        </label>
        <label class="block">
            <span class="text-xs font-semibold text-subtle uppercase">{{ __('offers.loan_calculator.max_dti') }} (%)</span>
            <input type="number" min="1" max="100" x-model.number="maxDtiPercent" class="mt-1 w-full rounded border border-placeholder px-3 py-2 text-sm">
        </label>
    </div>

    <div class="border border-placeholder bg-placeholder/10 p-6">
        <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('offers.loan_calculator.max_loan_amount') }}</p>
        <p class="mt-2 font-heading text-3xl font-bold text-primary" x-text="Math.max(0, Math.round(maxLoanAmount)).toLocaleString() + ' ֏'"></p>

        <p class="mt-6 text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('offers.loan_calculator.max_monthly_payment') }}</p>
        <p class="mt-2 font-heading text-xl font-semibold text-ink">
            <span x-text="Math.round(maxMonthlyPayment).toLocaleString()"></span> ֏ / {{ __('offers.per_month') }}
        </p>

        <p class="mt-6 text-xs leading-relaxed text-subtle">{{ __('offers.loan_calculator.disclaimer') }}</p>
    </div>
</div>
