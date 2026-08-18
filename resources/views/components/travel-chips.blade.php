@props([
    'name',
    'options',
    'label' => null,
    'multiple' => false,
    'selected' => null,
    'icons' => [],
    // Optional Alpine method name taking an option value and returning
    // whether that option is currently unavailable - used by the priorities
    // group to grey out the chips past its cap.
    'lock' => null,
])

@php
    // A multi-select posts an array, so the submitted name carries the []
    // suffix - but old() and the error bag are both keyed on the bare name.
    $field = $multiple ? $name . '[]' : $name;
    $current = old($name, $selected);
    $chosen = $multiple ? (array) ($current ?? []) : $current;
@endphp

{{-- fieldset/legend rather than a div and a <p>: these are genuine groups
     of related controls, and without it a screen reader reads each chip
     with no idea which question it answers. --}}
<fieldset {{ $attributes->only('class') }}>
    @if ($label)
        <legend class="mb-2 block text-body-sm text-ink-muted">{{ $label }}</legend>
    @endif

    <div class="flex flex-wrap gap-2">
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
                    {{-- x-model and friends are forwarded so the live summary
                         reads the same state the form submits, rather than a
                         second copy that has to be kept in step. --}}
                    {{ $attributes->except('class') }}
                    class="peer sr-only"
                >
                <span class="flex items-center gap-2 rounded-full border border-border-subtle bg-transparent px-4 py-2 text-body-sm text-on-surface transition-colors peer-checked:border-primary-container peer-checked:bg-primary-container peer-checked:text-on-primary-container peer-focus-visible:ring-2 peer-focus-visible:ring-travel-primary/40 peer-disabled:cursor-not-allowed peer-disabled:opacity-40 group-hover:border-outline">
                    @isset ($icons[$value])
                        <x-travel-icon :name="$icons[$value]" class="h-[18px] w-[18px]" />
                    @endisset
                    {{ $optionLabel }}
                </span>
            </label>
        @endforeach
    </div>

    @error($name)
        <p class="mt-2 text-body-sm text-error">{{ $message }}</p>
    @enderror
</fieldset>
