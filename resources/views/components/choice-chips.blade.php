@props([
    'name',
    'options',
    'label' => null,
    'hint' => null,
    'multiple' => false,
    'selected' => null,
    // Optional name of an Alpine method taking an option value and
    // returning whether that option is currently unavailable - used by the
    // priorities group to grey out the chips past its cap.
    'lock' => null,
])

@php
    // The submitted field name. A multi-select posts an array, so it carries
    // the [] suffix - but the error bag and old() are both keyed on the bare
    // name, hence the two variables.
    $field = $multiple ? $name . '[]' : $name;
    $current = old($name, $selected);
    $chosen = $multiple ? (array) ($current ?? []) : $current;
@endphp

{{-- fieldset/legend rather than a bare div and <p>: these are genuine
     groups of related controls, and without it a screen reader reads each
     chip with no idea which question it answers. --}}
<fieldset {{ $attributes->only('class') }}>
    @if ($label)
        <legend class="block text-sm font-medium text-ink">{{ $label }}</legend>
    @endif

    @if ($hint)
        <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
    @endif

    <div class="mt-2 flex flex-wrap gap-2">
        @foreach ($options as $value => $optionLabel)
            @php
                $isChosen = $multiple ? in_array((string) $value, array_map('strval', $chosen), true) : (string) $chosen === (string) $value;
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
                         can read the same state the form submits, instead of
                         a second copy that has to be kept in step. --}}
                    {{ $attributes->except('class') }}
                    class="peer sr-only"
                >
                <span class="flex items-center gap-1.5 rounded-full border border-border-muted px-3 py-1.5 text-sm text-ink transition peer-checked:border-primary peer-checked:bg-primary/5 peer-checked:font-medium peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 peer-disabled:cursor-not-allowed peer-disabled:opacity-40 group-hover:border-primary/60">
                    {{ $optionLabel }}
                </span>
            </label>
        @endforeach
    </div>

    @error($name)
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</fieldset>
