/**
 * The mortgage comparison table on /banks/mortgages.
 *
 * Eligibility, ranking and the monthly payment all depend on the property
 * price / down payment / term the visitor picks, so they have to be
 * recomputed in the browser. What they must NOT do is decide whether the
 * table exists at all: the rows are rendered by Blade for the values these
 * inputs start on (see the @php block in mortgage-offers-table.blade.php,
 * which mirrors the maths below), and this component only moves, hides and
 * relabels rows that are already on the page. Previously the whole table
 * lived in a <template x-for>, so the page painted without it and then grew
 * by ~975px the moment Alpine booted.
 *
 * Any change to isEligible/monthlyPayment/the sort has to be made in that
 * @php block too, or the first paint stops matching the first render.
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

    /**
     * What the current inputs make of the rendered rows: where each one goes
     * (row index -> position, absent when the row should be hidden), what it
     * costs a month, and how many are left. One offer per bank survives - the
     * cheapest it has that the visitor qualifies for.
     */
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
        // Integer-like keys, so this walks the banks in ascending id order -
        // which is what settles rows the sort below leaves tied.
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

    /**
     * en-US grouping, not the visitor's browser locale: every other figure on
     * this page is formatted by PHP's number_format, and the first paint of
     * these same numbers is server-rendered, so they have to agree.
     */
    format(value) {
        return Math.round(value).toLocaleString('en-US');
    },
});
