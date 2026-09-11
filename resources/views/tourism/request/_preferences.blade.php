@php
    // Single-choice pill groups. Rendered as real radios behind the pill so
    // they submit, tab and announce like a normal group - the pill is the
    // label, not a button pretending to be one.
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
            <span class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-4 py-2 text-xs font-medium text-gray-700 transition-colors peer-checked:border-travel-600 peer-checked:bg-travel-50 peer-checked:font-semibold peer-checked:text-travel-800 peer-checked:[&_[data-check]]:inline-flex peer-focus-visible:ring-2 peer-focus-visible:ring-travel-600/40 group-hover:border-travel-200 group-hover:bg-gray-50/70">
                <span data-check class="hidden shrink-0 text-travel-600">
                    <x-travel-icon name="check" class="h-3.5 w-3.5" />
                </span>
                <x-travel-icon name="shield" class="h-4 w-4 text-gray-500" />
                {{ __('tourism.request.insurance') }}
            </span>
        </label>
    </div>
</section>
