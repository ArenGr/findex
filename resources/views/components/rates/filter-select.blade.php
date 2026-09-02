@props([
    'label',
    'options',   // [['label' => ..., 'href' => ..., 'selected' => bool], ...]
])

{{--
    A labelled dropdown for the rates filter bar. Native <select> for
    accessibility and mobile; each option's value is the URL that applies it,
    so changing the select navigates - the same URL wiring the old anchor menu
    used, just presented as the approved single-row bar.
--}}
<label class="relative flex min-w-[9.5rem] flex-1 flex-col justify-center rounded-xl border border-placeholder bg-white px-4 py-2 sm:flex-none">
    <span class="text-[10px] font-medium uppercase tracking-wide text-muted">{{ $label }}</span>
    <select
        aria-label="{{ $label }}"
        @change="if ($event.target.value) window.location.href = $event.target.value"
        class="mt-0.5 w-full cursor-pointer appearance-none truncate bg-transparent pe-5 text-sm font-medium text-ink outline-none"
    >
        @foreach ($options as $option)
            <option value="{{ $option['href'] }}" @selected($option['selected'] ?? false)>{{ $option['label'] }}</option>
        @endforeach
    </select>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="pointer-events-none absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true">
        <path d="m6 9 6 6 6-6" />
    </svg>
</label>
