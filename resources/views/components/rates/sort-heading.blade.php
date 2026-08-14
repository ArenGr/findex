@props(['column', 'href', 'active' => false, 'direction' => 'asc'])

{{--
    A column heading that sorts, and says so.

    The arrow is drawn only on the column actually ordering the table, and it
    points the way the rows run - so "which column is this sorted by, and which
    end is the top" is answered by looking at the table rather than by
    remembering what was pressed. Screen readers get the same fact through
    aria-sort on the cell, which is why this sets it on the parent.
--}}
@php
    $ascending = $direction === 'asc';
    $label = __($ascending ? 'rates.sorted_asc' : 'rates.sorted_desc');
@endphp

<a
    href="{{ $href }}"
    @if ($active) aria-current="true" @endif
    {{-- The next press reverses the active column and starts a new one in the
    direction it is usually asked in, which the controller decides. --}}
    class="-my-3 inline-flex items-center gap-1 py-3 tracking-wider uppercase transition {{ $active ? 'text-ink' : 'hover:text-ink' }}"
>
    {{ $slot }}

    @if ($active)
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-3 shrink-0" aria-hidden="true">
            <path d="{{ $ascending ? 'm6 14 6-6 6 6' : 'm6 10 6 6 6-6' }}" />
        </svg>
        <span class="sr-only">{{ $label }}</span>
    @endif
</a>
