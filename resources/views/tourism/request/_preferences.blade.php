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

<section class="{{ $card }}">
    <div class="mb-6 flex items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-travel-primary/10">
            <x-travel-icon name="tune" class="h-5 w-5 text-travel-primary" />
        </span>
        <h2 class="text-headline-md">{{ __('tourism.request.section_preferences') }}</h2>
    </div>

    <div class="flex flex-col gap-6">
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
            <span class="flex items-center gap-2 rounded-full border border-border-subtle px-4 py-2 text-body-sm text-on-surface transition-colors peer-checked:border-travel-primary peer-checked:bg-travel-primary peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-travel-primary/40 hover:border-outline">
                <x-travel-icon name="shield" class="h-[18px] w-[18px]" />
                {{ __('tourism.request.insurance') }}
            </span>
        </label>
    </div>
</section>
