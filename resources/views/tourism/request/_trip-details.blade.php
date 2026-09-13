@php
    $showScript = app()->getLocale() !== 'hy';

    // The four search controls sit on one row from lg.
    $col = 'flex min-w-0 flex-col gap-1.5';
@endphp

<section class="{{ $card }}">
    <div class="flex flex-wrap items-start justify-between gap-6">
        <div class="flex items-start gap-3">
            <x-travel-section-icon name="flight_takeoff" done="tripComplete" />
            <div>
                <h2 class="{{ $cardHeading }}">{{ __('tourism.request.search_heading') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('tourism.request.search_sub') }}</p>
            </div>
        </div>

        @if ($showScript)
            <p class="hidden items-center gap-2 lg:flex" aria-hidden="true">
                <span class="font-script text-lg font-bold text-travel-700">{{ __('tourism.request.search_script') }}</span>
                {{-- Curls down towards the first field. --}}
                <svg class="h-8 w-10 fill-none stroke-travel-600" viewBox="0 0 40 32" aria-hidden="true">
                    <path d="M4 4c14-2 26 4 28 16" stroke-width="2" stroke-linecap="round" />
                    <path d="M26 18l6 3 1-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </p>
        @endif
    </div>

    <div class="mt-8 grid grid-cols-1 gap-x-5 gap-y-5 md:grid-cols-2 lg:grid-cols-[0.8fr_0.95fr_1.25fr_1.05fr_auto] lg:items-start">
        {{-- From --}}
        <div class="{{ $col }}">
            <label for="departure_location" class="{{ $label }}">{{ __('tourism.request.departure_location') }}</label>
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

        {{-- To --}}
        <div class="{{ $col }}" @click.outside="destinationPickerOpen = false">
            <label for="destination-search" class="{{ $label }}">{{ __('tourism.request.destination') }}</label>

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
                    class="absolute z-20 mt-1 w-full min-w-[16rem] rounded-xl border border-gray-200 bg-white shadow-lg"
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

            <div x-show="destinations.length" x-cloak class="flex flex-wrap gap-1.5">
                <template x-for="code in destinations" :key="code">
                    <span class="inline-flex items-center gap-1 rounded-full border border-travel-200 bg-travel-50 px-2.5 py-1 text-xs font-semibold text-travel-800">
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

            <label class="flex cursor-pointer items-center gap-2">
                <input
                    type="checkbox"
                    name="open_to_suggestions"
                    value="1"
                    x-model="openToSuggestions"
                    class="h-4 w-4 shrink-0 rounded border-gray-300 text-travel-600 focus:ring-travel-600"
                >
                <span class="text-xs text-gray-600">{{ __('tourism.request.open_to_suggestions') }}</span>
            </label>

            @error('destination_countries')
                <p class="text-xs text-error">{{ $message }}</p>
            @enderror
            <template x-for="code in destinations" :key="'price-' + code">
                <p x-show="@js($typicalPrices)[code]" x-cloak class="text-xs text-gray-500">
                    <span x-text="countryName(code)"></span>:
                    <span class="font-semibold text-travel-700" x-text="Number(@js($typicalPrices)[code]).toLocaleString('en-US') + ' {{ __('tourism.request.amd') }}'"></span>
                </p>
            </template>
        </div>

        {{-- Dates --}}
        <div class="{{ $col }}">
            <span class="{{ $label }}" id="dates-label">{{ __('tourism.request.dates_label') }}</span>

            {{-- Both dates in one field-shaped box rather than two boxes side by side. --}}
            <div
                class="flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2 py-2.5 transition-colors focus-within:border-travel-600 focus-within:ring-1 focus-within:ring-travel-600 @error('check_in') border-error @enderror @error('check_out') border-error @enderror"
                role="group"
                aria-labelledby="dates-label"
            >
                @foreach ([
                    ['name' => 'check_in', 'model' => 'checkIn', 'label' => __('tourism.request.check_in'), 'min' => null],
                    ['name' => 'check_out', 'model' => 'checkOut', 'label' => __('tourism.request.check_out'), 'min' => 'checkIn || null'],
                ] as $i => $date)
                    @if ($i)
                        <span class="shrink-0 text-sm text-gray-400" aria-hidden="true">–</span>
                    @endif
                    <input
                        type="date"
                        name="{{ $date['name'] }}"
                        id="{{ $date['name'] }}"
                        x-model="{{ $date['model'] }}"
                        required
                        aria-label="{{ $date['label'] }}"
                        @if ($date['min']) :min="{{ $date['min'] }}" @endif
                        class="travel-date w-full min-w-0 bg-transparent text-sm text-gray-800 focus:outline-none"
                    >
                @endforeach
            </div>

            <div class="flex w-fit rounded-lg border border-gray-200 bg-gray-50 p-0.5" role="group" aria-label="{{ __('tourism.request.dates_label') }}">
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
                        class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition-colors {{ $onNow ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800' }}"
                    >
                        {{ $mode['label'] }}
                    </button>
                @endforeach
            </div>

            <div x-show="datesAreFlexible" x-cloak class="flex flex-wrap gap-1.5">
                @foreach ($dateFlexibilityOptions as $value => $optionLabel)
                    <button
                        type="button"
                        @click="dateFlexibility = @js($value)"
                        :aria-pressed="dateFlexibility === @js($value)"
                        :class="dateFlexibility === @js($value) ? @js($pillOn) : @js($pillOff)"
                        class="{{ $pill }} {{ $pillOff }} px-3 py-1 text-[11px]"
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
        <div class="{{ $col }}">
            <span class="{{ $label }}" id="travelers-label">{{ __('tourism.request.travelers_label') }}</span>

            <div class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2" role="group" aria-labelledby="travelers-label">
                @foreach ([
                    ['key' => 'adults', 'label' => __('tourism.request.adults'), 'step' => 'stepAdults', 'min' => 1],
                    ['key' => 'children', 'label' => __('tourism.request.children'), 'step' => 'stepChildren', 'min' => 0],
                ] as $row)
                    <div class="flex items-center justify-between gap-2">
                        <span class="min-w-0 text-xs leading-tight break-words text-gray-700" id="count-{{ $row['key'] }}">{{ $row['label'] }}</span>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <button
                                type="button"
                                @click="{{ $row['step'] }}(-1)"
                                :disabled="{{ $row['key'] }} <= {{ $row['min'] }}"
                                aria-label="{{ __('tourism.request.decrease') }} {{ $row['label'] }}"
                                class="{{ $stepper }} h-7 w-7"
                            ><x-travel-icon name="minus" class="h-3.5 w-3.5" /></button>
                            <span
                                class="w-5 text-center text-sm font-bold text-gray-900 tabular-nums"
                                aria-live="polite"
                                aria-labelledby="count-{{ $row['key'] }}"
                                x-text="{{ $row['key'] }}"
                            ></span>
                            <button
                                type="button"
                                @click="{{ $row['step'] }}(1)"
                                :disabled="{{ $row['key'] }} >= {{ $row['key'] === 'adults' ? 20 : $maxChildren }}"
                                aria-label="{{ __('tourism.request.increase') }} {{ $row['label'] }}"
                                class="{{ $stepper }} h-7 w-7"
                            ><x-travel-icon name="plus" class="h-3.5 w-3.5" /></button>
                        </div>
                    </div>
                @endforeach

                <input type="hidden" name="adults" :value="adults">
                <input type="hidden" name="children" :value="children">
            </div>

            <div x-show="children > 0" x-cloak class="flex flex-col gap-1.5">
                <template x-for="(age, index) in childAges" :key="index">
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-semibold text-gray-600"
                            :for="'child_age_' + index"
                            x-text="@js(__('tourism.request.child_age', ['number' => ':n'])).replace(':n', index + 1)"
                        ></label>
                        <select
                            :id="'child_age_' + index"
                            :name="'child_ages[' + index + ']'"
                            x-model="childAges[index]"
                            class="w-full rounded-lg border border-gray-200 bg-white p-1.5 text-xs focus:border-travel-600 focus:outline-none"
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

        {{-- The search itself. --}}
        <div class="{{ $col }} md:col-span-2 lg:col-span-1">
            <span class="{{ $label }} hidden lg:block" aria-hidden="true">&nbsp;</span>
            <button type="button" @click="toContact()" class="{{ $navPrimary }} max-w-[12rem] justify-center px-5 py-2.5 text-center leading-snug whitespace-normal">
                {{ __('tourism.request.get_offers') }}
                <x-travel-icon name="arrow_forward" class="h-4 w-4" />
            </button>
        </div>
    </div>

    {{-- Everything the search row leaves out, one click away. --}}
    <div class="mt-7 border-t border-gray-100 pt-5">
        <button
            type="button"
            @click="goToStep(2)"
            class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 transition-colors hover:text-travel-700"
        >
            <x-travel-icon name="tune" class="h-4 w-4 text-travel-600" />
            {{ __('tourism.request.more_options') }}
            <svg class="h-4 w-4 fill-none stroke-current" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 9.5l6 6 6-6" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </button>
    </div>
</section>
