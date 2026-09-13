import Alpine from 'alpinejs';
import morph from '@alpinejs/morph';
import travelRequestForm from './travel-request-form.js';
import homeRatesPanel from './home-rates-panel.js';
import mortgageTable from './mortgage-table.js';

window.Alpine = Alpine;

// The /tourism request form's state.
Alpine.data('travelRequestForm', travelRequestForm);

// One panel of the homepage rates table.
Alpine.data('homeRatesPanel', homeRatesPanel);

// The /banks/mortgages calculator.
Alpine.data('mortgageTable', mortgageTable);

// Used by /rates to patch the filtered results in place.
Alpine.plugin(morph);

// Bank/organization comparison shortlist.
const COMPARE_STORAGE_KEY = 'findex.compareList';
const COMPARE_MAX = 3;

Alpine.store('compare', {
    items: JSON.parse(localStorage.getItem(COMPARE_STORAGE_KEY) || '[]'),

    has(slug) {
        return this.items.some((item) => item.slug === slug);
    },

    atLimit() {
        return this.items.length >= COMPARE_MAX;
    },

    toggle(organization) {
        if (this.has(organization.slug)) {
            this.items = this.items.filter((item) => item.slug !== organization.slug);
        } else if (!this.atLimit()) {
            this.items = [...this.items, organization];
        }

        this.persist();
    },

    remove(slug) {
        this.items = this.items.filter((item) => item.slug !== slug);
        this.persist();
    },

    clear() {
        this.items = [];
        this.persist();
    },

    persist() {
        localStorage.setItem(COMPARE_STORAGE_KEY, JSON.stringify(this.items));
    },
});

// The /rates map.
const mountRatesMaps = () => {
    document.querySelectorAll('[data-rates-map]').forEach(async (wrapper) => {
        const canvas = wrapper.querySelector('[data-rates-map-canvas]');
        const payload = wrapper.querySelector('[data-rates-map-payload]');

        if (!canvas || !payload) {
            return;
        }

        // Morph can hand back the same canvas with different rates behind it - a city filter, say.
        if (canvas.dataset.renderedFor === payload.textContent) {
            return;
        }

        canvas.dataset.renderedFor = payload.textContent;

        const { points, labels } = JSON.parse(payload.textContent);
        const { renderRatesMap } = await import('./rates-map.js');

        renderRatesMap(canvas, points, labels);
    });
};

document.addEventListener('DOMContentLoaded', mountRatesMaps);
window.addEventListener('rates:panel-updated', mountRatesMaps);

Alpine.start();
