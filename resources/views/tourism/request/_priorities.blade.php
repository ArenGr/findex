@php
    // Icons per priority, matching the design's chips.
    $priorityIcons = [
        'lowest_price' => 'sell',
        'best_value' => 'wallet',
        'better_hotel' => 'hotel',
        'direct_flight' => 'flight',
        'good_location' => 'map',
        'all_inclusive' => 'restaurant',
        'family_friendly' => 'family',
    ];
@endphp

{{-- A plain white card like the rest; the question is set apart by its
     content and the live cap counter rather than a tinted surface. --}}
<section class="{{ $card }} space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-3">
            <x-travel-section-icon name="star" done="prioritiesComplete" />
            <h2 class="{{ $cardHeading }}" id="priorities-label">{{ __('tourism.request.priorities_label') }}</h2>
        </div>

        {{-- Live so the cap is visible as it is approached, rather than only
             announcing itself by refusing a fourth click. --}}
        <span
            class="rounded-full border border-travel-200 bg-travel-50 px-2.5 py-1 text-xs font-semibold text-travel-700"
            aria-live="polite"
            x-text="@js(__('tourism.request.priorities_counter', ['count' => ':c', 'max' => ':m']))
                .replace(':c', priorities.length)
                .replace(':m', maxPriorities)"
        ></span>
    </div>

    <p class="text-xs text-gray-500">
        {{ __('tourism.request.priorities_hint_agencies', ['max' => $maxPriorities]) }}
    </p>

    <div class="flex flex-wrap gap-2.5 pt-1" role="group" aria-labelledby="priorities-label">
        @foreach ($priorityOptions as $value => $optionLabel)
            <label
                for="priority-{{ $value }}"
                class="group cursor-pointer"
                :class="priorityLocked(@js($value)) && 'cursor-not-allowed'"
            >
                <input
                    type="checkbox"
                    name="priorities[]"
                    id="priority-{{ $value }}"
                    value="{{ $value }}"
                    x-model="priorities"
                    :disabled="priorityLocked(@js($value))"
                    class="peer sr-only"
                >
                {{-- The check icon marks selection as well as the colour, so
                     the state does not rest on colour alone. --}}
                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-xs font-medium text-gray-700 transition-all peer-checked:border-travel-600 peer-checked:bg-travel-50 peer-checked:font-semibold peer-checked:text-travel-800 peer-focus-visible:ring-2 peer-focus-visible:ring-travel-600/40 peer-disabled:cursor-not-allowed peer-disabled:opacity-40 group-hover:border-travel-500">
                    <x-travel-icon
                        name="check"
                        class="hidden h-4 w-4 text-travel-600"
                        {{-- Js::from spelled out rather than @js: Blade does not
                             compile directives inside a component's attribute, so
                             @js reached Alpine verbatim and the expression was a
                             syntax error. --}}
                        ::class="priorityChosen({{ Illuminate\Support\Js::from($value) }}) ? 'inline' : 'hidden'"
                    />
                    @isset ($priorityIcons[$value])
                        <x-travel-icon
                            :name="$priorityIcons[$value]"
                            class="h-4 w-4 text-gray-500"
                            ::class="priorityChosen({{ Illuminate\Support\Js::from($value) }}) ? 'hidden' : 'inline'"
                        />
                    @endisset
                    {{ $optionLabel }}
                </span>
            </label>
        @endforeach
    </div>

    @error('priorities')
        <p class="text-xs text-error">{{ $message }}</p>
    @enderror
    @foreach ($errors->get('priorities.*') as $messages)
        <p class="text-xs text-error">{{ $messages[0] }}</p>
    @endforeach
</section>
