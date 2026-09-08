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
        departure: config.departure,
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

        // The insurance checkbox is x-model'd in _preferences.blade.php. It was
        // never declared here, so Alpine threw "insurance is not defined" on
        // every render of step 2 and the checkbox did not bind at all.
        insurance: config.insurance,

        // Budget
        budgetBand: config.budgetBand,
        budgetMin: config.budgetMin,
        budgetMax: config.budgetMax,
        // Opens already expanded when a custom range came back from a failed
        // submission, so the traveller can see the values being complained
        // about instead of an empty section.
        customBudgetOpen: Boolean(config.budgetMin || config.budgetMax),

        mobileSummaryOpen: false,

        // Multi-step wizard. The form stays a single POST - these only govern
        // which fields are on screen. initialStep lets a failed submission
        // reopen the step whose field the server rejected.
        step: config.initialStep || 1,
        totalSteps: 3,
        consented: config.consented,

        /* ---------------------------------------------------------------
         * Slider
         *
         * The three steps are one carousel inside the wizard card, not three
         * stacked panels: only the active screen occupies space, and moving
         * between them slides horizontally rather than opening a section
         * further down the page.
         * ------------------------------------------------------------- */

        /** Measured height of the active screen; drives the viewport. */
        slideHeight: 0,

        /**
         * False until the first measurement lands.
         *
         * Until then the active screen stays in normal flow so the card has
         * its natural height on the server-rendered paint. Taking every screen
         * out of flow before knowing how tall the active one is would collapse
         * the card to nothing and then snap it open once Alpine booted.
         */
        sliderReady: false,

        init() {
            // A children count restored from old() can arrive without a
            // matching set of ages (or with too many); the form must always
            // render exactly one age field per child.
            this.syncChildAges();

            this.$nextTick(() => this.startSlider());
        },

        startSlider() {
            const measure = () => {
                const active = this.$refs[`slide${this.step}`];

                if (active) {
                    this.slideHeight = active.offsetHeight;
                }
            };

            measure();
            // Flipped after the first measurement, so the switch out of flow
            // happens at exactly the height the card already had.
            this.sliderReady = true;

            // Screens change height on their own too - adding a child age
            // field, opening the custom budget - so the viewport follows them
            // rather than only re-measuring on navigation.
            if (typeof ResizeObserver !== 'undefined') {
                const observer = new ResizeObserver(() => measure());

                for (let n = 1; n <= this.totalSteps; n += 1) {
                    const el = this.$refs[`slide${n}`];

                    if (el) {
                        observer.observe(el);
                    }
                }
            }

            this.$watch('step', () => this.$nextTick(measure));
        },

        /**
         * Where a screen sits relative to the one on show: behind it, on show,
         * or ahead of it. Object form, so Alpine removes whichever classes the
         * server printed that no longer apply.
         */
        slideClass(n) {
            const active = this.step === n;

            return {
                // Out of flow once measured; before that only the inactive
                // ones are, so the active screen still sets the height.
                'absolute inset-x-0 top-0': this.sliderReady || ! active,
                'opacity-0 pointer-events-none': ! active,
                '-translate-x-10': n < this.step,
                'translate-x-10': n > this.step,
            };
        },

        /* ---------------------------------------------------------------
         * Wizard navigation
         * ------------------------------------------------------------- */

        goToStep(n) {
            const target = Math.min(this.totalSteps, Math.max(1, n));

            if (target === this.step) {
                return;
            }

            this.step = target;
            this.scrollToTop();

            // Focus follows the slide, so a keyboard or screen-reader user is
            // moved to the screen that just replaced the one they were on.
            this.$nextTick(() => this.$refs[`heading${target}`]?.focus({ preventScroll: true }));
        },

        /** Advances only if the current step's own required fields are valid,
         *  so a step is never left half-answered. The form itself is novalidate
         *  and the server is the real gate; this is a courtesy check. */
        next() {
            if (!this.validateStep(this.step)) {
                return;
            }

            this.goToStep(this.step + 1);
        },

        back() {
            this.goToStep(this.step - 1);
        },

        validateStep(n) {
            const panel = document.querySelector(`[data-step="${n}"]`);

            if (!panel) {
                return true;
            }

            for (const control of panel.querySelectorAll('input, select, textarea')) {
                if (control.disabled || control.type === 'hidden') {
                    continue;
                }

                if (!control.checkValidity()) {
                    control.reportValidity();

                    return false;
                }
            }

            return true;
        },

        scrollToTop() {
            const anchor = document.getElementById('travel-form-top');

            if (anchor) {
                anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },

        /** Whether a step is far enough along to show a tick in the stepper. */
        stepDone(n) {
            if (n === 1) return this.tripComplete;
            if (n === 2) return this.preferencesComplete && this.budgetComplete;

            return false;
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

        /* ---------------------------------------------------------------
         * Section completion - drives the tick that appears in a section's
         * icon once it holds enough to be useful to an agency.
         * ------------------------------------------------------------- */

        get tripComplete() {
            return Boolean(
                (this.departure || '').trim() &&
                    (this.destinations.length || this.openToSuggestions) &&
                    this.checkIn &&
                    this.checkOut,
            );
        },

        get preferencesComplete() {
            return Boolean(this.flightPreference && this.hotelPreference && this.mealPreference);
        },

        get prioritiesComplete() {
            return this.priorities.length > 0;
        },

        get budgetComplete() {
            return Boolean(this.budgetBand) || this.usingCustomBudget;
        },

        /* ---------------------------------------------------------------
         * Itinerary - the headline version shown once a trip takes shape
         * ------------------------------------------------------------- */

        /** True once anything worth summarising has been entered. */
        get hasItinerary() {
            return Boolean(
                (this.departure || '').trim() ||
                    this.destinations.length ||
                    this.openToSuggestions ||
                    (this.checkIn && this.checkOut),
            );
        },

        /**
         * True once the visitor has told us anything at all.
         *
         * Wider than hasItinerary, which only covers where and when: someone
         * who skipped ahead and picked a budget has given us something, and
         * the summary showing "Nothing here yet" underneath it would be wrong.
         * Mirrored server-side in _summary.blade.php so the right branch is
         * the one that paints first.
         */
        get hasAnyDetail() {
            return Boolean(
                this.hasItinerary ||
                    this.budgetBand ||
                    this.usingCustomBudget ||
                    this.priorities.length,
            );
        },

        /** "Yerevan → Dubai" (or whichever half exists). */
        get itineraryRoute() {
            const to = this.destinations.length
                ? this.destinations.map((code) => this.countryName(code)).join(', ')
                : this.openToSuggestions
                  ? this.labels.openToSuggestions
                  : '';
            const from = (this.departure || '').trim();

            if (from && to) {
                return `${from} → ${to}`;
            }

            return from || to || '';
        },

        /** "Sep 14 – Sep 21 · 7 nights · 2 adults" - the meta beneath the route. */
        get itineraryMeta() {
            const parts = [];

            if (this.checkIn && this.checkOut) {
                parts.push(this.datesSummary);

                if (this.nights) {
                    parts.push(`${this.nights} ${this.labels.nights}`);
                }
            }

            parts.push(this.travellersSummary);

            return parts.join(' · ');
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
