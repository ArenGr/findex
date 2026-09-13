@props([
    'name',
    'options',
    'label' => null,
    'multiple' => false,
    'selected' => null,
    'icons' => [],
    'lock' => null,
])

@php
    $field = $multiple ? $name . '[]' : $name;
    $current = old($name, $selected);
    $chosen = $multiple ? (array) ($current ?? []) : $current;

    // The page's one pill, not a copy of it - see request.blade.php.
    $pill = 'inline-flex items-center gap-1.5 rounded-full border px-4 py-2 text-xs transition-colors';
@endphp

<fieldset {{ $attributes->only('class') }}>
    @if ($label)
        <legend class="mb-2.5 block text-xs font-semibold text-gray-600">{{ $label }}</legend>
    @endif

    <div class="flex flex-wrap gap-2.5">
        @foreach ($options as $value => $optionLabel)
            @php
                $isChosen = $multiple
                    ? in_array((string) $value, array_map('strval', $chosen), true)
                    : (string) $chosen === (string) $value;
                $id = Str::slug($name . '-' . $value);
            @endphp

            <label for="{{ $id }}" class="group cursor-pointer">
                <input
                    type="{{ $multiple ? 'checkbox' : 'radio' }}"
                    name="{{ $field }}"
                    id="{{ $id }}"
                    value="{{ $value }}"
                    @checked($isChosen)
                    @if ($lock) x-bind:disabled="{{ $lock }}(@js((string) $value))" @endif
                    {{ $attributes->except('class') }}
                    class="peer sr-only"
                >
                <span class="{{ $pill }} border-gray-200 bg-white font-medium text-gray-700 peer-checked:border-travel-600 peer-checked:bg-travel-50 peer-checked:font-semibold peer-checked:text-travel-800 peer-checked:[&_[data-check]]:inline-flex peer-focus-visible:ring-2 peer-focus-visible:ring-travel-600/40 peer-disabled:cursor-not-allowed peer-disabled:opacity-40 group-hover:border-travel-200 group-hover:bg-gray-50/70">
                    <span data-check class="hidden shrink-0 text-travel-600">
                        <x-travel-icon name="check" class="h-3.5 w-3.5" />
                    </span>
                    @isset ($icons[$value])
                        <x-travel-icon :name="$icons[$value]" class="h-4 w-4 text-gray-500" />
                    @endisset
                    {{ $optionLabel }}
                </span>
            </label>
        @endforeach
    </div>

    @error($name)
        <p class="mt-2 text-xs text-error">{{ $message }}</p>
    @enderror
</fieldset>
