/**
 * One currency/rate-type panel of the homepage rates table.
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
