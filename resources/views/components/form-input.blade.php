@props(['name', 'label', 'type' => 'text', 'required' => false])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name) }}"
        @if ($required) required @endif
        {{ $attributes->class([
            'mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none',
            'border-red-400 focus:border-red-500' => $errors->has($name),
            'border-border-muted focus:border-primary' => !$errors->has($name),
        ]) }}
    >

    @error($name)
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
