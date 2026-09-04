/**
 * One currency/rate-type panel of the homepage rates table.
 *
 * The rows themselves are rendered by Blade (see rates-table-row.blade.php),
 * so this holds only what a sort needs: the two numbers per row it can key
 * on. It hands back the position each row should occupy, which the markup
 * feeds to CSS `order` - sorting reorders rows that are already painted
 * rather than re-creating them, which is what kept the table from existing
 * at all until Alpine had booted.
 *
 * Registered here rather than written inline for the same reason as
 * travelRequestForm: the homepage renders 53 of these panels, and an inline
 * copy of the object costs ~1.5 KB of markup each.
 *
 * @param {Array<{buy_rate: number, sell_rate: number}>} metrics - one entry
 *        per row, in the order Blade rendered them.
 */
export default (metrics) => ({
    metrics,
    sortKey: 'sell_rate',
    sortDir: 'asc',

    toggleSort(key) {
        this.sortDir = this.sortKey === key && this.sortDir === 'asc' ? 'desc' : 'asc';
        this.sortKey = key;
    },

    /** Row index (as rendered) -> the position the current sort puts it in. */
    get positions() {
        const ranked = this.metrics
            .map((_, index) => index)
            .sort((a, b) => (this.metrics[a][this.sortKey] - this.metrics[b][this.sortKey])
                * (this.sortDir === 'asc' ? 1 : -1));

        const positions = [];
        ranked.forEach((rowIndex, position) => (positions[rowIndex] = position));

        return positions;
    },
});
