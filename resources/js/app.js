import Alpine from 'alpinejs';
import morph from '@alpinejs/morph';
import travelRequestForm from './travel-request-form.js';

window.Alpine = Alpine;

// The /tourism request form's state. Registered as a named component rather
// than written inline: the object is far too large to read inside an x-data
// attribute, and a single double quote in one would close the attribute and
// silently drop every property after it.
Alpine.data('travelRequestForm', travelRequestForm);

// Used by /rates to patch the filtered results in place. Morphing rather than
// replacing innerHTML keeps the existing nodes, so nothing unchanged repaints
// and focus, scroll and open popovers survive a filter change.
Alpine.plugin(morph);

// Bank/organization comparison shortlist. Stored in localStorage (not a
// server session) so it works for guests and survives normal full-page
// navigation between the directory, an organization page, and /compare.
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

// The /rates map. Deliberately not an Alpine component: #rates-panel is morphed
// on every filter click, and Alpine does not run x-init over a subtree that
// arrives that way - so the map appeared on a direct URL load and silently did
// not when you pressed "Map". Mounted explicitly instead, on load and after
// each morph.
//
// The Leaflet import stays dynamic so its 149KB never reaches anyone who does
// not open the map.
const mountRatesMaps = () => {
    document.querySelectorAll('[data-rates-map]').forEach(async (wrapper) => {
        const canvas = wrapper.querySelector('[data-rates-map-canvas]');
        const payload = wrapper.querySelector('[data-rates-map-payload]');

        if (!canvas || !payload) {
            return;
        }

        // Morph can hand back the same canvas with different rates behind it -
        // a city filter, say. Re-rendering only when the data actually changed
        // keeps a filter click from rebuilding an identical map.
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
