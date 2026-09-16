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
    $pill = 'inline-flex items-center gap-1.5 min-h-11 rounded-lg border px-4 text-sm font-medium transition';
@endphp

<fieldset {{ $attributes->only('class') }}>
    @if ($label)
        <legend class="mb-2 block text-[13px] font-semibold text-ink">{{ $label }}</legend>
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
                <span class="{{ $pill }} border-placeholder bg-white text-muted peer-checked:border-primary peer-checked:bg-primary peer-checked:text-white peer-checked:[&_[data-check]]:inline-flex peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 peer-disabled:cursor-not-allowed peer-disabled:opacity-40 group-hover:border-primary group-hover:text-primary peer-checked:group-hover:bg-primary-dark peer-checked:group-hover:text-white">
                    <span data-check class="hidden shrink-0 text-current">
                        <x-travel-icon name="check" class="h-3.5 w-3.5" />
                    </span>
                    @isset ($icons[$value])
                        <x-travel-icon :name="$icons[$value]" class="h-4 w-4 text-muted" />
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
