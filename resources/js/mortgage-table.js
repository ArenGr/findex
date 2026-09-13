/**
 * The mortgage comparison table on /banks/mortgages.
 *
 * @param {object} config - defaults and row data, supplied by the view.
 */
export default (config) => ({
    currencyTab: config.currency,
    propertyPrice: config.propertyPrice,
    downPaymentPercent: config.downPaymentPercent,
    termMonths: config.termMonths,
    offersByCurrency: config.offersByCurrency,

    get loanAmount() {
        return Math.max(0, (this.propertyPrice[this.currencyTab] || 0) * (1 - this.downPaymentPercent / 100));
    },

    isEligible(row, loanAmount) {
        return loanAmount >= row.min_amount
            && loanAmount <= row.max_amount
            && this.downPaymentPercent >= row.min_down_payment_percent
            && this.termMonths >= row.term_min_months
            && this.termMonths <= row.term_max_months;
    },

    monthlyPayment(ratePercent, principal, months) {
        const r = ratePercent / 100 / 12;
        if (months <= 0) return 0;
        if (r === 0) return principal / months;
        return principal * r * Math.pow(1 + r, months) / (Math.pow(1 + r, months) - 1);
    },

    get view() {
        const rows = this.offersByCurrency[this.currencyTab] || [];
        const loanAmount = this.loanAmount;
        const bestPerBank = {};

        rows.forEach((row, index) => {
            if (!this.isEligible(row, loanAmount)) return;

            if (bestPerBank[row.id] === undefined || row.eff_rate < rows[bestPerBank[row.id]].eff_rate) {
                bestPerBank[row.id] = index;
            }
        });

        const payments = {};
        const ranked = Object.values(bestPerBank);

        ranked.forEach((index) => {
            payments[index] = this.monthlyPayment(rows[index].eff_rate, loanAmount, this.termMonths);
        });

        ranked.sort((a, b) => rows[a].eff_rate - rows[b].eff_rate || payments[a] - payments[b]);

        const positions = {};
        ranked.forEach((rowIndex, position) => (positions[rowIndex] = position));

        return { positions, payments, count: ranked.length };
    },

    /** Rank shown in the badge; blank while the row is filtered out. */
    rank(index) {
        const position = this.view.positions[index];

        return position === undefined ? '' : position + 1;
    },

    /** Monthly payment; blank while the row is filtered out. */
    paymentLabel(index) {
        const payment = this.view.payments[index];

        return payment === undefined ? '' : this.format(payment);
    },

    format(value) {
        return Math.round(value).toLocaleString('en-US');
    },
});
