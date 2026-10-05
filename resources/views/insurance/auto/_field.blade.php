{{-- One labelled field with a leading icon, as on the rates panel. --}}
@php
    // A field may answer for more than one posted name (the email is sent to
    // the insurer and kept for the results link), so show whichever failed.
    $message = collect([$field['name'], ...($field['error_names'] ?? [])])
        ->map(fn ($name) => $errors->first($name))
        ->first(fn ($first) => $first !== '');
@endphp

<div class="min-w-0 {{ $field['wrapper'] ?? '' }}">
    <label for="{{ $field['name'] }}" class="block text-sm font-semibold text-ink">{{ $field['label'] }}</label>
    <div class="relative mt-2.5">
        <x-lucide :name="$field['icon']" :size="20" class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-subtle" />
        <input
            type="{{ $field['type'] }}"
            name="{{ $field['name'] }}"
            id="{{ $field['name'] }}"
            value="{{ old($field['name']) }}"
            @isset($field['model']) x-model="{{ $field['model'] }}" @endisset
            @isset($field['input']) @input="{{ $field['input'] }}" @endisset
            @isset($field['placeholder']) placeholder="{{ $field['placeholder'] }}" @endisset
            @isset($field['autocomplete']) autocomplete="{{ $field['autocomplete'] }}" @endisset
            @isset($field['inputmode']) inputmode="{{ $field['inputmode'] }}" @endisset
            @isset($field['maxlength']) maxlength="{{ $field['maxlength'] }}" @endisset
            @if ($message) aria-invalid="true" @endif
            required
            class="field field-icon {{ $field['class'] ?? '' }}"
        >
    </div>
    @if ($message)<p class="mt-2 text-sm text-accent-red">{{ $message }}</p>@endif
    @isset($field['hint'])<p class="mt-2 text-sm leading-relaxed text-muted">{{ $field['hint'] }}</p>@endisset
</div>
