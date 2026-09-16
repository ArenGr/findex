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

<section class="{{ $card }} space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-3">
            <x-travel-section-icon name="star" done="prioritiesComplete" />
            <h2 class="{{ $cardHeading }}" id="priorities-label">{{ __('tourism.request.priorities_label') }}</h2>
        </div>

        <span
            class="rounded-full border border-border bg-surface-alt px-2.5 py-1 text-xs font-semibold text-primary-dark"
            aria-live="polite"
            x-text="@js(__('tourism.request.priorities_counter', ['count' => ':c', 'max' => ':m']))
                .replace(':c', priorities.length)
                .replace(':m', maxPriorities)"
        ></span>
    </div>

    <p class="text-xs text-muted">
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
                <span class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-placeholder bg-white px-4 text-sm font-medium text-muted transition peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 peer-disabled:cursor-not-allowed peer-disabled:opacity-40 group-hover:border-primary group-hover:text-primary peer-checked:group-hover:bg-primary-dark peer-checked:group-hover:text-white">
                    <x-travel-icon
                        name="check"
                        class="hidden h-4 w-4 text-current"
                        ::class="priorityChosen({{ Illuminate\Support\Js::from($value) }}) ? 'inline' : 'hidden'"
                    />
                    @isset ($priorityIcons[$value])
                        <x-travel-icon
                            :name="$priorityIcons[$value]"
                            class="h-4 w-4 text-current"
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
