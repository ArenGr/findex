<section class="{{ $card }}">
    <div class="mb-5 flex items-center gap-3">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-travel-primary/10 transition-colors" :class="tripComplete && '!bg-travel-primary'">
            <x-travel-icon name="check" class="h-[18px] w-[18px] text-white" x-show="tripComplete" x-cloak />
            <x-travel-icon name="flight_takeoff" class="h-[18px] w-[18px] text-travel-primary" x-show="!tripComplete" />
        </span>
        <h2 class="text-headline-md">{{ __('tourism.request.section_trip') }}</h2>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        {{-- Departing from --}}
        <div class="flex flex-col gap-1">
            <label for="departure_location" class="{{ $label }}">{{ __('tourism.request.departure_location') }}</label>
            <input
                type="text"
                name="departure_location"
                id="departure_location"
                value="{{ old('departure_location') }}"
                x-model="departure"
                required
                autocomplete="off"
                placeholder="{{ __('tourism.request.departure_location_placeholder') }}"
                class="{{ $field }} @error('departure_location') border-error @enderror"
            >
            @error('departure_location')
                <p class="text-body-sm text-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- Destinations. Several are allowed (see QuoteRequest::MAX_DESTINATIONS);
             naming none is also valid, as long as "open to suggestions" is
             ticked - the two are cross-checked server-side. --}}
        <div class="flex flex-col gap-1.5" @click.outside="destinationPickerOpen = false">
            <label for="destination-search" class="{{ $label }}">{{ __('tourism.request.destination') }}</label>

            {{-- Selected destinations, shown as removable chips above the input.
                 They carry the real submitted values (country codes). --}}
            <div x-show="destinations.length" x-cloak class="flex flex-wrap gap-2">
                <template x-for="code in destinations" :key="code">
                    <span class="inline-flex items-center gap-1 rounded-full bg-travel-primary/10 px-3 py-1 text-body-sm text-travel-primary">
                        <span x-text="countryFlag(code)"></span>
                        <span x-text="countryName(code)"></span>
                        <button
                            type="button"
                            @click="removeDestination(code)"
                            class="rounded-full p-0.5 transition-colors hover:bg-travel-primary/20 focus-visible:ring-2 focus-visible:ring-travel-primary focus-visible:outline-none"
                            :aria-label="@js(__('tourism.request.destination_remove', ['destination' => ':name'])).replace(':name', countryName(code))"
                        >
                            <x-travel-icon name="close" class="h-4 w-4" />
                        </button>
                        <input type="hidden" name="destination_countries[]" :value="code">
                    </span>
                </template>
            </div>

            {{-- A real, always-visible input. Typing filters the country list;
                 picking one adds it as a chip. It stays a country picker under
                 the hood because the server validates against real country
                 codes - free text would be rejected. --}}
            <div x-show="!destinationsFull" class="relative">
                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-muted">
                    <x-travel-icon name="location" class="h-[17px] w-[17px]" />
                </span>
                <input
                    type="text"
                    id="destination-search"
                    x-model="destinationSearch"
                    x-ref="destinationSearch"
                    autocomplete="off"
                    @focus="destinationPickerOpen = true"
                    @keydown.escape="destinationPickerOpen = false"
                    :placeholder="destinations.length ? @js(__('tourism.request.destination_add_another')) : @js(__('tourism.request.destination_placeholder'))"
                    class="{{ $field }} pl-10"
                >

                <div
                    x-show="destinationPickerOpen"
                    x-cloak
                    x-transition
                    class="absolute z-20 mt-1 w-full rounded-lg border border-border-subtle bg-white shadow-lg"
                >
                    <ul class="max-h-64 overflow-y-auto p-1">
                        <template x-for="country in availableCountries.slice(0, 60)" :key="country.code">
                            <li>
                                <button
                                    type="button"
                                    @click="addDestination(country.code)"
                                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-body-sm text-on-surface hover:bg-travel-primary/5"
                                >
                                    <span x-text="country.flag"></span>
                                    <span x-text="country.name"></span>
                                </button>
                            </li>
                        </template>
                        <li x-show="!availableCountries.length" class="px-3 py-2 text-body-sm text-ink-muted">
                            {{ __('tourism.request.destination_no_results') }}
                        </li>
                    </ul>
                </div>
            </div>

            <p x-show="destinationsFull" x-cloak class="text-body-sm text-ink-muted">
                {{ __('tourism.request.destination_limit', ['max' => $maxDestinations]) }}
            </p>

            {{-- Add another (focuses the input), only once at least one is chosen. --}}
            <button
                type="button"
                x-show="destinations.length && !destinationsFull"
                x-cloak
                @click="destinationPickerOpen = true; $nextTick(() => $refs.destinationSearch.focus())"
                class="w-fit text-body-sm font-medium text-travel-primary hover:underline focus-visible:ring-2 focus-visible:ring-travel-primary focus-visible:outline-none"
            >
                {{ __('tourism.request.destination_add') }}
            </button>

            <label class="mt-1 flex cursor-pointer items-center gap-2">
                <input
                    type="checkbox"
                    name="open_to_suggestions"
                    value="1"
                    x-model="openToSuggestions"
                    class="h-4 w-4 rounded border-border-subtle text-travel-primary focus:ring-travel-primary"
                >
                <span class="text-body-sm text-ink-muted">{{ __('tourism.request.open_to_suggestions') }}</span>
            </label>

            @error('destination_countries')
                <p class="mt-1 text-body-sm text-error">{{ $message }}</p>
            @enderror

            {{-- The typical-price teaser only has data for the curated
                 destinations, so it shows for the first one that has any. --}}
            <template x-for="code in destinations" :key="'price-' + code">
                <p x-show="@js($typicalPrices)[code]" x-cloak class="text-body-sm text-ink-muted">
                    <span x-text="countryName(code)"></span>:
                    <span class="font-semibold text-travel-primary" x-text="Number(@js($typicalPrices)[code]).toLocaleString('en-US') + ' {{ __('tourism.request.amd') }}'"></span>
                </p>
            </template>
        </div>

        {{-- Dates: a segmented control switching between exact days and a
             flexibility window. The window's options only appear once
             flexible is chosen, so the default state stays compact. --}}
        <div class="flex flex-col gap-1">
            <span class="{{ $label }}" id="dates-label">{{ __('tourism.request.dates_label') }}</span>

            <div class="mb-3 flex w-fit rounded-lg border border-border-subtle bg-surface-container-low p-1" role="group" aria-labelledby="dates-label">
                <button
                    type="button"
                    @click="setDateMode(false)"
                    :aria-pressed="!datesAreFlexible"
                    :class="!datesAreFlexible ? 'bg-white text-on-surface shadow-[0_1px_3px_rgba(24,29,18,0.12)]' : 'text-ink-muted hover:text-on-surface'"
                    class="rounded-md px-4 py-2 text-body-sm font-medium transition-colors"
                >
                    {{ __('tourism.request.dates_exact') }}
                </button>
                <button
                    type="button"
                    @click="setDateMode(true)"
                    :aria-pressed="datesAreFlexible"
                    :class="datesAreFlexible ? 'bg-white text-on-surface shadow-[0_1px_3px_rgba(24,29,18,0.12)]' : 'text-ink-muted hover:text-on-surface'"
                    class="rounded-md px-4 py-2 text-body-sm font-medium transition-colors"
                >
                    {{ __('tourism.request.dates_flexible') }}
                </button>
            </div>

            {{-- Two separate, labelled fields - a joined box read as one
                 control to a screen reader and gave the two dates no identity
                 of their own. --}}
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="check_in" class="{{ $label }}">{{ __('tourism.request.check_in') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-muted">
                            <x-travel-icon name="calendar_month" class="h-[18px] w-[18px]" />
                        </span>
                        <input
                            type="date"
                            name="check_in"
                            id="check_in"
                            x-model="checkIn"
                            required
                            class="{{ $field }} pl-10 @error('check_in') border-error @enderror"
                        >
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <label for="check_out" class="{{ $label }}">{{ __('tourism.request.check_out') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-muted">
                            <x-travel-icon name="calendar_month" class="h-[18px] w-[18px]" />
                        </span>
                        <input
                            type="date"
                            name="check_out"
                            id="check_out"
                            x-model="checkOut"
                            required
                            :min="checkIn || null"
                            class="{{ $field }} pl-10 @error('check_out') border-error @enderror"
                        >
                    </div>
                </div>
            </div>

            <div x-show="datesAreFlexible" x-cloak class="mt-3 flex flex-wrap gap-2">
                @foreach ($dateFlexibilityOptions as $value => $optionLabel)
                    <button
                        type="button"
                        @click="dateFlexibility = @js($value)"
                        :aria-pressed="dateFlexibility === @js($value)"
                        :class="dateFlexibility === @js($value)
                            ? 'border-travel-primary bg-travel-primary text-white'
                            : 'border-border-subtle text-on-surface hover:border-outline'"
                        class="rounded-full border px-4 py-2 text-body-sm transition-colors"
                    >
                        {{ $optionLabel }}
                    </button>
                @endforeach
            </div>

            <input type="hidden" name="date_flexibility" :value="dateFlexibility" :disabled="!datesAreFlexible">

            @error('check_in')
                <p class="text-body-sm text-error">{{ $message }}</p>
            @enderror
            @error('check_out')
                <p class="text-body-sm text-error">{{ $message }}</p>
            @enderror
            @error('date_flexibility')
                <p class="text-body-sm text-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- Travellers --}}
        <div class="flex flex-col gap-1">
            <span class="{{ $label }}" id="travelers-label">{{ __('tourism.request.travelers_label') }}</span>

            <div class="flex flex-col gap-3 rounded-lg border border-border-subtle bg-white p-4" role="group" aria-labelledby="travelers-label">
                @foreach ([
                    ['key' => 'adults', 'label' => __('tourism.request.adults'), 'step' => 'stepAdults', 'min' => 1],
                    ['key' => 'children', 'label' => __('tourism.request.children'), 'step' => 'stepChildren', 'min' => 0],
                ] as $row)
                    <div class="flex items-center justify-between">
                        <span class="text-body-sm" id="count-{{ $row['key'] }}">{{ $row['label'] }}</span>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="{{ $row['step'] }}(-1)"
                                :disabled="{{ $row['key'] }} <= {{ $row['min'] }}"
                                aria-label="{{ __('tourism.request.decrease') }} {{ $row['label'] }}"
                                class="{{ $stepper }}"
                            >&minus;</button>
                            <span
                                class="w-6 text-center font-bold tabular-nums"
                                aria-live="polite"
                                aria-labelledby="count-{{ $row['key'] }}"
                                x-text="{{ $row['key'] }}"
                            ></span>
                            <button
                                type="button"
                                @click="{{ $row['step'] }}(1)"
                                :disabled="{{ $row['key'] }} >= {{ $row['key'] === 'adults' ? 20 : $maxChildren }}"
                                aria-label="{{ __('tourism.request.increase') }} {{ $row['label'] }}"
                                class="{{ $stepper }}"
                            >+</button>
                        </div>
                    </div>
                @endforeach

                <input type="hidden" name="adults" :value="adults">
                <input type="hidden" name="children" :value="children">

                {{-- One age field per child. An agency prices a 2-year-old and
                     a 15-year-old very differently, so this is asked rather
                     than assumed. --}}
                <div x-show="children > 0" x-cloak class="border-t border-border-subtle pt-3">
                    <template x-for="(age, index) in childAges" :key="index">
                        <div class="mb-2 last:mb-0">
                            <label
                                class="mb-1 block text-body-sm text-ink-muted"
                                :for="'child_age_' + index"
                                x-text="@js(__('tourism.request.child_age', ['number' => ':n'])).replace(':n', index + 1)"
                            ></label>
                            <select
                                :id="'child_age_' + index"
                                :name="'child_ages[' + index + ']'"
                                x-model="childAges[index]"
                                class="w-full rounded border border-border-subtle bg-white p-2 text-body-sm focus:border-travel-primary focus:outline-none"
                            >
                                <option value="">{{ __('tourism.request.summary_not_set') }}</option>
                                <template x-for="option in childAgeOptions" :key="option">
                                    <option
                                        :value="option"
                                        x-text="@js(__('tourism.request.child_age_years', ['count' => ':n'])).replace(':n', option)"
                                    ></option>
                                </template>
                            </select>
                        </div>
                    </template>
                </div>
            </div>

            @error('adults')
                <p class="text-body-sm text-error">{{ $message }}</p>
            @enderror
            @error('child_ages')
                <p class="text-body-sm text-error">{{ $message }}</p>
            @enderror
            @foreach ($errors->get('child_ages.*') as $messages)
                <p class="text-body-sm text-error">{{ $messages[0] }}</p>
            @endforeach
        </div>

        {{-- A specific hotel, if the traveller already has one in mind. --}}
        <div class="flex flex-col gap-1 md:col-span-2">
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
                <p class="text-body-sm text-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
</section>
