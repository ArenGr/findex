{{-- One labelled field with a leading icon, as on the rates panel. --}}
<div class="min-w-0">
    <label for="{{ $field['name'] }}" class="block text-[13px] font-semibold text-ink">{{ $field['label'] }}</label>
    <div class="relative mt-2">
        <x-lucide :name="$field['icon']" :size="18" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-subtle" />
        <input
            type="{{ $field['type'] }}"
            name="{{ $field['name'] }}"
            id="{{ $field['name'] }}"
            value="{{ old($field['name']) }}"
            @isset($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endisset
            @error($field['name']) aria-invalid="true" @enderror
            required
            class="field field-icon {{ $field['class'] ?? '' }}"
        >
    </div>
    @error($field['name'])<p class="mt-1.5 text-xs text-accent-red">{{ $message }}</p>@enderror
    @isset($field['hint'])<p class="mt-1.5 text-xs leading-relaxed text-muted">{{ $field['hint'] }}</p>@endisset
</div>
