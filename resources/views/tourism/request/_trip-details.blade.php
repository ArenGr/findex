<section class="{{ $card }} space-y-6">
    <div class="flex items-center gap-3">
        <x-travel-section-icon name="flight_takeoff" done="tripComplete" />
        <h2 class="{{ $cardHeading }}">{{ __('tourism.request.section_trip') }}</h2>
    </div>

    <div class="grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2">
        {{-- Departing from --}}
        <div class="flex flex-col gap-1">
            <label for="departure_location" class="{{ $label }}">{{ __('tourism.request.departure_location') }}</label>
            {{-- Same leading glyph as the destination field beside it: the two
                 sit on one row and read as a pair, and only one of them
                 carrying an icon made the row look unfinished. --}}
            <div class="relative">
                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-gray-400">
                    <x-travel-icon name="flight_takeoff" class="h-[17px] w-[17px]" />
                </span>
                <input
                    type="text"
                    name="departure_location"
                    id="departure_location"
                    value="{{ old('departure_location') }}"
                    x-model="departure"
                    required
                    autocomplete="off"
                    placeholder="{{ __('tourism.request.departure_location_placeholder') }}"
                    class="{{ $fieldIcon }} @error('departure_location') border-error @enderror"
                >
            </div>
            @error('departure_location')
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-1.5" @click.outside="destinationPickerOpen = false">
            <label for="destination-search" class="{{ $label }}">{{ __('tourism.request.destination') }}</label>
            <div x-show="destinations.length" x-cloak class="flex flex-wrap gap-2">
                <template x-for="code in destinations" :key="code">
                    <span class="inline-flex items-center gap-1 rounded-full border border-travel-200 bg-travel-50 px-3 py-1 text-xs font-semibold text-travel-800">
                        <span x-text="countryFlag(code)"></span>
                        <span x-text="countryName(code)"></span>
                        <button
                            type="button"
                            @click="removeDestination(code)"
                            class="rounded-full p-0.5 transition-colors hover:bg-travel-200 focus-visible:ring-2 focus-visible:ring-travel-600 focus-visible:outline-none"
                            :aria-label="@js(__('tourism.request.destination_remove', ['destination' => ':name'])).replace(':name', countryName(code))"
                        >
                            <x-travel-icon name="close" class="h-3.5 w-3.5" />
                        </button>
                        <input type="hidden" name="destination_countries[]" :value="code">
                    </span>
                </template>
            </div>
            <div x-show="!destinationsFull" class="relative">
                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-gray-400">
                    <x-travel-icon name="location_on" class="h-[17px] w-[17px]" />
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
                    class="{{ $fieldIcon }}"
                >

                <div
                    x-show="destinationPickerOpen"
                    x-cloak
                    x-transition
                    class="absolute z-20 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg"
                >
                    <ul class="max-h-64 overflow-y-auto p-1">
                        <template x-for="country in availableCountries.slice(0, 60)" :key="country.code">
                            <li>
                                <button
                                    type="button"
                                    @click="addDestination(country.code)"
                                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-travel-50"
                                >
                                    <span x-text="country.flag"></span>
                                    <span x-text="country.name"></span>
                                </button>
                            </li>
                        </template>
                        <li x-show="!availableCountries.length" class="px-3 py-2 text-sm text-gray-500">
                            {{ __('tourism.request.destination_no_results') }}
                        </li>
                    </ul>
                </div>
            </div>

            <p x-show="destinationsFull" x-cloak class="text-xs text-gray-500">
                {{ __('tourism.request.destination_limit', ['max' => $maxDestinations]) }}
            </p>
            <button
                type="button"
                x-show="destinations.length && !destinationsFull"
                x-cloak
                @click="destinationPickerOpen = true; $nextTick(() => $refs.destinationSearch.focus())"
                class="w-fit text-xs font-bold text-travel-600 hover:text-travel-700 hover:underline focus-visible:ring-2 focus-visible:ring-travel-600 focus-visible:outline-none"
            >
                {{ __('tourism.request.destination_add') }}
            </button>

            <label class="mt-1 flex cursor-pointer items-center gap-2">
                <input
                    type="checkbox"
                    name="open_to_suggestions"
                    value="1"
                    x-model="openToSuggestions"
                    class="h-4 w-4 rounded border-gray-300 text-travel-600 focus:ring-travel-600"
                >
                <span class="text-xs text-gray-600">{{ __('tourism.request.open_to_suggestions') }}</span>
            </label>

            @error('destination_countries')
                <p class="mt-1 text-xs text-error">{{ $message }}</p>
            @enderror
            <template x-for="code in destinations" :key="'price-' + code">
                <p x-show="@js($typicalPrices)[code]" x-cloak class="text-xs text-gray-500">
                    <span x-text="countryName(code)"></span>:
                    <span class="font-semibold text-travel-700" x-text="Number(@js($typicalPrices)[code]).toLocaleString('en-US') + ' {{ __('tourism.request.amd') }}'"></span>
                </p>
            </template>
        </div>
        <div class="flex flex-col gap-1.5">
            <span class="{{ $label }}" id="dates-label">{{ __('tourism.request.dates_label') }}</span>

            <div class="mb-2.5 flex w-fit rounded-lg border border-gray-200 bg-gray-50 p-1" role="group" aria-labelledby="dates-label">
                @php $flexibleInitially = (bool) old('date_flexibility'); @endphp
                @foreach ([
                    ['flexible' => false, 'label' => __('tourism.request.dates_exact')],
                    ['flexible' => true,  'label' => __('tourism.request.dates_flexible')],
                ] as $mode)
                    @php
                        $on = $mode['flexible'] ? 'datesAreFlexible' : '!datesAreFlexible';
                        $onNow = $mode['flexible'] === $flexibleInitially;
                    @endphp
                    <button
                        type="button"
                        @click="setDateMode({{ $mode['flexible'] ? 'true' : 'false' }})"
                        :aria-pressed="{{ $on }}"
                        :class="{
                            'bg-white text-gray-900 shadow-sm': {{ $on }},
                            'text-gray-500 hover:text-gray-800': !({{ $on }}),
                        }"
                        class="rounded-md px-4 py-1.5 text-xs font-semibold transition-colors {{ $onNow ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800' }}"
                    >
                        {{ $mode['label'] }}
                    </button>
                @endforeach
            </div>
            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label for="check_in" class="{{ $label }}">{{ __('tourism.request.check_in') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-gray-400">
                            <x-travel-icon name="calendar_month" class="h-[18px] w-[18px]" />
                        </span>
                        <input
                            type="date"
                            name="check_in"
                            id="check_in"
                            x-model="checkIn"
                            required
                            class="{{ $fieldIcon }} travel-date @error('check_in') border-error @enderror"
                        >
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <label for="check_out" class="{{ $label }}">{{ __('tourism.request.check_out') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-gray-400">
                            <x-travel-icon name="calendar_month" class="h-[18px] w-[18px]" />
                        </span>
                        <input
                            type="date"
                            name="check_out"
                            id="check_out"
                            x-model="checkOut"
                            required
                            :min="checkIn || null"
                            class="{{ $fieldIcon }} travel-date @error('check_out') border-error @enderror"
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
                        :class="dateFlexibility === @js($value) ? @js($pillOn) : @js($pillOff)"
                        class="{{ $pill }} {{ $pillOff }}"
                    >
                        {{ $optionLabel }}
                    </button>
                @endforeach
            </div>

            <input type="hidden" name="date_flexibility" :value="dateFlexibility" :disabled="!datesAreFlexible">

            @error('check_in')
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror
            @error('check_out')
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror
            @error('date_flexibility')
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror
        </div>

        {{-- Travellers --}}
        <div class="flex flex-col gap-1.5">
            <span class="{{ $label }}" id="travelers-label">{{ __('tourism.request.travelers_label') }}</span>

            <div class="flex flex-col gap-2.5 rounded-xl border border-gray-200 bg-white p-3.5" role="group" aria-labelledby="travelers-label">
                @foreach ([
                    ['key' => 'adults', 'label' => __('tourism.request.adults'), 'step' => 'stepAdults', 'min' => 1],
                    ['key' => 'children', 'label' => __('tourism.request.children'), 'step' => 'stepChildren', 'min' => 0],
                ] as $row)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700" id="count-{{ $row['key'] }}">{{ $row['label'] }}</span>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                @click="{{ $row['step'] }}(-1)"
                                :disabled="{{ $row['key'] }} <= {{ $row['min'] }}"
                                aria-label="{{ __('tourism.request.decrease') }} {{ $row['label'] }}"
                                class="{{ $stepper }}"
                            ><x-travel-icon name="minus" class="h-4 w-4" /></button>
                            <span
                                class="w-6 text-center text-sm font-bold text-gray-900 tabular-nums"
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
                            ><x-travel-icon name="plus" class="h-4 w-4" /></button>
                        </div>
                    </div>
                @endforeach

                <input type="hidden" name="adults" :value="adults">
                <input type="hidden" name="children" :value="children">
                <div x-show="children > 0" x-cloak class="border-t border-gray-200 pt-3">
                    <template x-for="(age, index) in childAges" :key="index">
                        <div class="mb-2 last:mb-0">
                            <label
                                class="mb-1 block text-xs font-semibold text-gray-600"
                                :for="'child_age_' + index"
                                x-text="@js(__('tourism.request.child_age', ['number' => ':n'])).replace(':n', index + 1)"
                            ></label>
                            <select
                                :id="'child_age_' + index"
                                :name="'child_ages[' + index + ']'"
                                x-model="childAges[index]"
                                class="w-full rounded-lg border border-gray-200 bg-white p-2 text-sm focus:border-travel-600 focus:outline-none"
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
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror
            @error('child_ages')
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror
            @foreach ($errors->get('child_ages.*') as $messages)
                <p class="text-xs text-error">{{ $messages[0] }}</p>
            @endforeach
        </div>
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
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror
        </div>
    </div>
    <div class="flex justify-end">
        <button type="button" @click="next()" class="{{ $navPrimary }}">
            {{ __('tourism.request.wizard_continue_prefs') }}
            <x-travel-icon name="arrow_forward" class="h-4 w-4" />
        </button>
    </div>
</section>
