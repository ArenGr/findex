import Alpine from 'alpinejs';

window.Alpine = Alpine;

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

Alpine.start();
