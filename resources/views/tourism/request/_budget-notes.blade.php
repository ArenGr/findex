<section class="{{ $card }} space-y-6">
    <div class="flex items-center gap-3">
        <x-travel-section-icon name="wallet" done="budgetComplete" />
        <h2 class="{{ $cardHeading }}">{{ __('tourism.request.section_budget_notes') }}</h2>
    </div>

    {{-- The bands and the note side by side, so the card does not run to the height of the two stacked. --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:gap-8">
        <div>
            <span class="{{ $label }} mb-2.5" id="budget-label">{{ __('tourism.request.budget_band_label') }}</span>

            <div class="mb-3 flex flex-wrap gap-2.5" role="group" aria-labelledby="budget-label">
                @foreach ($budgetBandLabels as $value => $optionLabel)
                    <button
                        type="button"
                        @click="selectBudgetBand(@js($value))"
                        :aria-pressed="budgetBand === @js($value)"
                        :class="budgetBand === @js($value) ? @js($pillOn) : @js($pillOff)"
                        class="{{ $pill }} {{ $pillOff }}"
                    >
                        <x-travel-icon name="check" class="h-3.5 w-3.5 shrink-0 text-travel-600" x-show="budgetBand === {{ Illuminate\Support\Js::from($value) }}" x-cloak />
                        {{ $optionLabel }}
                    </button>
                @endforeach
            </div>

            <input type="hidden" name="budget_band" :value="budgetBand" :disabled="!budgetBand">

            <button
                type="button"
                x-show="!customBudgetOpen"
                @click="openCustomBudget()"
                class="text-xs font-bold text-travel-600 hover:text-travel-700 hover:underline focus-visible:ring-2 focus-visible:ring-travel-600/40 focus-visible:outline-none"
            >
                {{ __('tourism.request.budget_custom_toggle') }}
            </button>

            {{-- Hidden until asked for, so the default state stays as compact as the design. --}}
            <div x-show="customBudgetOpen" x-cloak class="mt-4">
                <p class="{{ $label }} mb-2">{{ __('tourism.request.budget_custom_heading') }}</p>
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
                        <span class="absolute top-1/2 right-4 -translate-y-1/2 text-xs font-medium text-gray-400">{{ __('tourism.request.amd') }}</span>
                    </div>
                    @error('budget_min_amd')
                        <p class="text-xs text-error">{{ $message }}</p>
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
                        <span class="absolute top-1/2 right-4 -translate-y-1/2 text-xs font-medium text-gray-400">{{ __('tourism.request.amd') }}</span>
                    </div>
                    @error('budget_max_amd')
                        <p class="text-xs text-error">{{ $message }}</p>
                    @enderror
                </div>
                </div>
            </div>

            @error('budget_band')
                <p class="mt-2 text-xs text-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1 lg:h-full">
            <label for="notes" class="{{ $label }}">{{ __('tourism.request.notes_optional') }}</label>
            <textarea
                name="notes"
                id="notes"
                rows="5"
                placeholder="{{ __('tourism.request.notes_placeholder') }}"
                class="{{ $field }} min-h-[128px] resize-y lg:h-full @error('notes') border-error @enderror"
            >{{ old('notes') }}</textarea>
            @error('notes')
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>
