// State for the auto insurance request wizard (/insurance/auto).
export default function autoInsuranceForm(config) {
    return {
        step: config.initialStep || 1,
        totalSteps: 4,
        loading: false,

        plate: config.plate,
        term: config.term,
        name: config.name,
        email: config.email,
        phone: config.phone,
        bankAccount: config.bankAccount,
        labels: config.labels,

        goToStep(n) {
            const target = Math.min(this.totalSteps, Math.max(1, n));

            if (target === this.step) {
                return;
            }

            this.step = target;
            this.scrollToTop();

            this.$nextTick(() => this.$refs[`heading${target}`]?.focus({ preventScroll: true }));
        },

        next() {
            if (this.validateStep(this.step)) {
                this.goToStep(this.step + 1);
            }
        },

        back() {
            this.goToStep(this.step - 1);
        },

        // The step's own fields, checked by the browser - the server checks them again.
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
            document.getElementById('insurance-form-top')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        /** Whether a step holds everything it asked for, so the stepper can tick it. */
        stepDone(n) {
            if (n === 1) return Boolean(this.plate.trim() && this.term);
            if (n === 2) return Boolean(this.name.trim() && this.email.trim() && this.phone.trim());
            if (n === 3) return Boolean(this.bankAccount.trim());

            return false;
        },

        get plateSummary() {
            return this.plate.trim().toUpperCase() || this.labels.notSet;
        },

        get termSummary() {
            return this.labels.terms[this.term] ?? this.labels.notSet;
        },

        get contactSummary() {
            return [this.email.trim(), this.phone.trim()].filter(Boolean).join(' · ') || this.labels.notSet;
        },

        /** Only the last four digits, so the page never repeats the account back in full. */
        get bankSummary() {
            const digits = this.bankAccount.replace(/\D/g, '');

            return digits ? `•••• •••• ${digits.slice(-4)}` : this.labels.notSet;
        },
    };
}
