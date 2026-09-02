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
<section class="{{ $card }}">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-travel-primary/10 transition-colors" :class="prioritiesComplete && '!bg-travel-primary'">
                <x-travel-icon name="check" class="h-[18px] w-[18px] text-white" x-show="prioritiesComplete" x-cloak />
                <x-travel-icon name="star" class="h-[18px] w-[18px] text-travel-primary" x-show="!prioritiesComplete" />
            </span>
            <h2 class="text-headline-md" id="priorities-label">{{ __('tourism.request.priorities_label') }}</h2>
        </div>

        {{-- Live so the cap is visible as it is approached, rather than only
             announcing itself by refusing a fourth click. --}}
        <span
            class="rounded-full bg-travel-primary/10 px-2.5 py-1 text-label-caps font-medium text-travel-primary"
            aria-live="polite"
            x-text="@js(__('tourism.request.priorities_counter', ['count' => ':c', 'max' => ':m']))
                .replace(':c', priorities.length)
                .replace(':m', maxPriorities)"
        ></span>
    </div>

    <p class="mb-6 text-body-sm text-ink-muted">
        {{ __('tourism.request.priorities_hint_agencies', ['max' => $maxPriorities]) }}
    </p>

    <div class="flex flex-wrap gap-2" role="group" aria-labelledby="priorities-label">
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
                <span class="flex items-center gap-2 rounded-full border border-border-subtle bg-white px-4 py-2 text-body-sm text-on-surface transition-colors peer-checked:border-travel-primary peer-checked:bg-travel-primary/10 peer-checked:font-medium peer-checked:text-travel-primary peer-focus-visible:ring-2 peer-focus-visible:ring-travel-primary/40 peer-disabled:cursor-not-allowed peer-disabled:opacity-40 group-hover:border-outline">
                    <x-travel-icon
                        name="check"
                        class="hidden h-[18px] w-[18px] peer-checked:group-[]:inline"
                        ::class="priorityChosen(@js($value)) ? 'inline' : 'hidden'"
                    />
                    @isset ($priorityIcons[$value])
                        <x-travel-icon
                            :name="$priorityIcons[$value]"
                            class="h-[18px] w-[18px]"
                            ::class="priorityChosen(@js($value)) ? 'hidden' : 'inline'"
                        />
                    @endisset
                    {{ $optionLabel }}
                </span>
            </label>
        @endforeach
    </div>

    @error('priorities')
        <p class="mt-3 text-body-sm text-error">{{ $message }}</p>
    @enderror
    @foreach ($errors->get('priorities.*') as $messages)
        <p class="mt-3 text-body-sm text-error">{{ $messages[0] }}</p>
    @endforeach
</section>
