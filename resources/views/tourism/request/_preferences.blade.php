@php
    // Single-choice pill groups.
    $groups = [
        ['name' => 'flight_preference', 'label' => __('tourism.request.flights_label'), 'options' => $flightOptions, 'model' => 'flightPreference'],
        ['name' => 'hotel_preference', 'label' => __('tourism.request.hotel_class_label'), 'options' => $hotelOptions, 'model' => 'hotelPreference'],
        ['name' => 'meal_preference', 'label' => __('tourism.request.meals_label'), 'options' => $mealOptions, 'model' => 'mealPreference'],
    ];
@endphp

<section class="{{ $card }} space-y-6">
    <div class="flex items-center gap-3">
        <x-travel-section-icon name="tune" done="preferencesComplete" />
        <h2 class="{{ $cardHeading }}">{{ __('tourism.request.section_preferences') }}</h2>
    </div>

    <div class="space-y-6">
        @foreach ($groups as $group)
            <x-travel-chips
                :name="$group['name']"
                :label="$group['label']"
                :options="$group['options']"
                x-model="{{ $group['model'] }}"
            />
        @endforeach

        <label class="group inline-flex cursor-pointer">
            <input type="checkbox" name="insurance" value="1" x-model="insurance" class="peer sr-only">
            <span class="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-placeholder bg-white px-4 text-sm font-medium text-muted transition peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-checked:[&_[data-check]]:inline-flex peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 group-hover:border-primary group-hover:text-primary peer-checked:group-hover:bg-primary-dark peer-checked:group-hover:text-white">
                <span data-check class="hidden shrink-0 text-current">
                    <x-travel-icon name="check" class="h-3.5 w-3.5" />
                </span>
                <x-travel-icon name="shield" class="h-4 w-4 text-current" />
                {{ __('tourism.request.insurance') }}
            </span>
        </label>

        <div class="flex flex-col gap-1">
            <label for="hotel_name" class="{{ $label }}">{{ __('tourism.request.hotel_name') }}</label>
            <input
                type="text"
                name="hotel_name"
                id="hotel_name"
                value="{{ old('hotel_name') }}"
                placeholder="{{ __('tourism.request.hotel_name_placeholder') }}"
                class="{{ $field }}"
            >
            @error('hotel_name')
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>
