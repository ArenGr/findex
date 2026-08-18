/**
 * State for the travel request form (/tourism).
 *
 * A single source of truth for everything the traveller picks: the summary
 * panel, the mobile action bar and the form inputs all read from here, so
 * the summary can never disagree with what is about to be submitted.
 *
 * Lives in a module rather than an x-data attribute because an object this
 * size is unreadable inline - and because a single double quote anywhere
 * inside an x-data="..." attribute closes it and silently discards every
 * property after that point.
 */
export default function travelRequestForm(config) {
    return {
        countries: config.countries,
        labels: config.labels,

        // Trip
        destinations: config.destinations,
        openToSuggestions: config.openToSuggestions,
        maxDestinations: config.maxDestinations,
        destinationPickerOpen: false,
        destinationSearch: '',

        checkIn: config.checkIn,
        checkOut: config.checkOut,
        dateFlexibility: config.dateFlexibility,

        adults: config.adults,
        children: config.children,
        childAges: config.childAges,
        maxChildren: config.maxChildren,
        maxChildAge: config.maxChildAge,

        // Preferences
        flightPreference: config.flightPreference,
        hotelPreference: config.hotelPreference,
        mealPreference: config.mealPreference,
        priorities: config.priorities,
        maxPriorities: config.maxPriorities,

        // Budget
        budgetBand: config.budgetBand,
        budgetMin: config.budgetMin,
        budgetMax: config.budgetMax,
        // Opens already expanded when a custom range came back from a failed
        // submission, so the traveller can see the values being complained
        // about instead of an empty section.
        customBudgetOpen: Boolean(config.budgetMin || config.budgetMax),

        mobileSummaryOpen: false,

        init() {
            // A children count restored from old() can arrive without a
            // matching set of ages (or with too many); the form must always
            // render exactly one age field per child.
            this.syncChildAges();
        },

        /* ---------------------------------------------------------------
         * Destinations
         * ------------------------------------------------------------- */

        get destinationsFull() {
            return this.destinations.length >= this.maxDestinations;
        },

        get availableCountries() {
            const query = this.destinationSearch.toLowerCase();

            return this.countries.filter(
                (country) =>
                    !this.destinations.includes(country.code) &&
                    (!query || country.name.toLowerCase().includes(query)),
            );
        },

        countryName(code) {
            return this.countries.find((country) => country.code === code)?.name ?? code;
        },

        countryFlag(code) {
            return this.countries.find((country) => country.code === code)?.flag ?? '';
        },

        addDestination(code) {
            if (!this.destinations.includes(code) && !this.destinationsFull) {
                this.destinations = [...this.destinations, code];
            }

            this.destinationSearch = '';
            this.destinationPickerOpen = false;
        },

        removeDestination(code) {
            this.destinations = this.destinations.filter((existing) => existing !== code);
        },

        /* ---------------------------------------------------------------
         * Dates
         * ------------------------------------------------------------- */

        get datesAreFlexible() {
            return this.dateFlexibility !== '';
        },

        setDateMode(flexible) {
            // Switching back to exact dates has to clear the window, or the
            // form would submit a flexibility the traveller just withdrew.
            this.dateFlexibility = flexible ? this.dateFlexibility || 'plus_3' : '';
        },

        get nights() {
            if (!this.checkIn || !this.checkOut) {
                return null;
            }

            const diff = Math.round((new Date(this.checkOut) - new Date(this.checkIn)) / 86400000);

            return diff > 0 ? diff : null;
        },

        /* ---------------------------------------------------------------
         * Travellers
         * ------------------------------------------------------------- */

        stepAdults(by) {
            this.adults = Math.min(20, Math.max(1, this.adults + by));
        },

        stepChildren(by) {
            this.children = Math.min(this.maxChildren, Math.max(0, this.children + by));
            this.syncChildAges();
        },

        /**
         * Keeps one age per child. Growing adds empty ages rather than a
         * guessed default - an age nobody chose is worse than a visibly
         * unanswered field, because the traveller cannot tell it is wrong.
         */
        syncChildAges() {
            const ages = this.childAges.slice(0, this.children);

            while (ages.length < this.children) {
                ages.push('');
            }

            this.childAges = ages;
        },

        get childAgeOptions() {
            return Array.from({ length: this.maxChildAge + 1 }, (_, age) => age);
        },

        /* ---------------------------------------------------------------
         * Priorities
         * ------------------------------------------------------------- */

        get prioritiesFull() {
            return this.priorities.length >= this.maxPriorities;
        },

        priorityChosen(value) {
            return this.priorities.includes(value);
        },

        /** Locked rather than hidden: a traveller at the cap needs to see what they would be swapping out. */
        priorityLocked(value) {
            return this.prioritiesFull && !this.priorityChosen(value);
        },

        togglePriority(value) {
            if (this.priorityChosen(value)) {
                this.priorities = this.priorities.filter((existing) => existing !== value);
            } else if (!this.prioritiesFull) {
                this.priorities = [...this.priorities, value];
            }
        },

        /* ---------------------------------------------------------------
         * Budget
         * ------------------------------------------------------------- */

        selectBudgetBand(band) {
            this.budgetBand = band;
            // A band and a custom range are two answers to one question, so
            // picking a band drops the range rather than leaving both to be
            // reconciled server-side.
            this.budgetMin = '';
            this.budgetMax = '';
            this.customBudgetOpen = false;
        },

        openCustomBudget() {
            this.customBudgetOpen = true;
            this.budgetBand = '';
        },

        get usingCustomBudget() {
            return this.customBudgetOpen && Boolean(this.budgetMin || this.budgetMax);
        },

        /* ---------------------------------------------------------------
         * Summary - derived, never stored separately
         * ------------------------------------------------------------- */

        get destinationSummary() {
            if (this.destinations.length) {
                return this.destinations.map((code) => this.countryName(code)).join(', ');
            }

            return this.openToSuggestions ? this.labels.openToSuggestions : this.labels.notSet;
        },

        get datesSummary() {
            if (!this.checkIn || !this.checkOut) {
                return this.labels.notSet;
            }

            const format = (value) =>
                new Date(value).toLocaleDateString(document.documentElement.lang || 'en', {
                    month: 'short',
                    day: 'numeric',
                });

            return `${format(this.checkIn)} – ${format(this.checkOut)}`;
        },

        get travellersSummary() {
            const parts = [`${this.adults} ${this.labels.adults.toLowerCase()}`];

            if (this.children > 0) {
                parts.push(`${this.children} ${this.labels.children.toLowerCase()}`);
            }

            return parts.join(', ');
        },

        get flightSummary() {
            return this.labels.flight[this.flightPreference] ?? this.labels.notSet;
        },

        get hotelSummary() {
            return this.labels.hotel[this.hotelPreference] ?? this.labels.notSet;
        },

        get mealsSummary() {
            return this.labels.meals[this.mealPreference] ?? this.labels.notSet;
        },

        get budgetSummary() {
            if (this.usingCustomBudget) {
                const from = this.budgetMin ? Number(this.budgetMin).toLocaleString('en-US') : '';
                const to = this.budgetMax ? Number(this.budgetMax).toLocaleString('en-US') : '';

                return from && to ? `${from} – ${to}` : from || to;
            }

            return this.budgetBand ? this.labels.budget[this.budgetBand] : this.labels.notSet;
        },

        /** The one-line version for the mobile bar - destination, dates, party size. */
        get compactSummary() {
            const parts = [];

            if (this.destinations.length) {
                parts.push(this.destinations.map((code) => this.countryName(code)).join(', '));
            } else if (this.openToSuggestions) {
                parts.push(this.labels.openToSuggestions);
            }

            if (this.checkIn && this.checkOut) {
                parts.push(this.datesSummary);
            }

            parts.push(this.travellersSummary);

            return parts.join(' · ');
        },
    };
}
