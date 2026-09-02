<section class="{{ $card }}">
    <div class="mb-5 flex items-center gap-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-travel-primary/10 transition-colors" :class="budgetComplete && '!bg-travel-primary'">
                <x-travel-icon name="check" class="h-[18px] w-[18px] text-white" x-show="budgetComplete" x-cloak />
                <x-travel-icon name="wallet" class="h-[18px] w-[18px] text-travel-primary" x-show="!budgetComplete" />
            </span>
        <h2 class="text-headline-md">{{ __('tourism.request.section_budget_notes') }}</h2>
    </div>

    <div class="flex flex-col gap-6">
        <div>
            <span class="{{ $label }} mb-2" id="budget-label">{{ __('tourism.request.budget_band_label') }}</span>

            {{-- Bands are buttons rather than radios because picking one also
                 clears any custom range - a plain radio would leave both
                 answers set and the server having to guess which was meant. --}}
            <div class="mb-3 flex flex-wrap gap-2" role="group" aria-labelledby="budget-label">
                @foreach ($budgetBandLabels as $value => $optionLabel)
                    <button
                        type="button"
                        @click="selectBudgetBand(@js($value))"
                        :aria-pressed="budgetBand === @js($value)"
                        :class="budgetBand === @js($value)
                            ? 'border-travel-primary bg-travel-primary/10 font-medium text-travel-primary'
                            : 'border-border-subtle bg-white text-on-surface hover:border-outline'"
                        class="flex items-center gap-2 rounded-full border px-4 py-2.5 text-body-sm transition-colors focus-visible:ring-2 focus-visible:ring-travel-primary/40 focus-visible:outline-none"
                    >
                        <x-travel-icon name="check" class="h-[16px] w-[16px] shrink-0" x-show="budgetBand === @js($value)" x-cloak />
                        {{ $optionLabel }}
                    </button>
                @endforeach
            </div>

            <input type="hidden" name="budget_band" :value="budgetBand" :disabled="!budgetBand">

            <button
                type="button"
                x-show="!customBudgetOpen"
                @click="openCustomBudget()"
                class="text-body-sm font-medium text-travel-primary hover:underline focus-visible:ring-2 focus-visible:ring-travel-primary/40 focus-visible:outline-none"
            >
                {{ __('tourism.request.budget_custom_toggle') }}
            </button>

            {{-- Hidden until asked for, so the default state stays as compact
                 as the design. --}}
            <div x-show="customBudgetOpen" x-cloak class="mt-4">
                <p class="{{ $label }} mb-2 font-medium text-on-surface">{{ __('tourism.request.budget_custom_heading') }}</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="budget_min_amd" class="{{ $label }}">{{ __('tourism.request.budget_custom_from') }}</label>
                    <div class="relative">
                        <input
                            type="number"
                            name="budget_min_amd"
                            id="budget_min_amd"
                            min="0"
                            step="1000"
                            x-model="budgetMin"
                            class="{{ $field }} pr-14 @error('budget_min_amd') border-error @enderror"
                        >
                        <span class="absolute top-1/2 right-4 -translate-y-1/2 text-body-sm text-ink-muted">{{ __('tourism.request.amd') }}</span>
                    </div>
                    @error('budget_min_amd')
                        <p class="text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-1">
                    <label for="budget_max_amd" class="{{ $label }}">{{ __('tourism.request.budget_custom_to') }}</label>
                    <div class="relative">
                        <input
                            type="number"
                            name="budget_max_amd"
                            id="budget_max_amd"
                            min="0"
                            step="1000"
                            x-model="budgetMax"
                            class="{{ $field }} pr-14 @error('budget_max_amd') border-error @enderror"
                        >
                        <span class="absolute top-1/2 right-4 -translate-y-1/2 text-body-sm text-ink-muted">{{ __('tourism.request.amd') }}</span>
                    </div>
                    @error('budget_max_amd')
                        <p class="text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
                </div>
            </div>

            @error('budget_band')
                <p class="mt-2 text-body-sm text-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1">
            <label for="notes" class="{{ $label }}">{{ __('tourism.request.notes_optional') }}</label>
            <p class="mb-1 text-body-sm text-ink-muted">{{ __('tourism.request.notes_helper') }}</p>
            <textarea
                name="notes"
                id="notes"
                rows="5"
                placeholder="{{ __('tourism.request.notes_placeholder') }}"
                class="{{ $field }} min-h-[128px] resize-y @error('notes') border-error @enderror"
            >{{ old('notes') }}</textarea>
            @error('notes')
                <p class="text-body-sm text-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>
