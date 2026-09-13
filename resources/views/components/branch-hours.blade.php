@props(['branch'])

@php
    $open = $branch->isOpenAt();
@endphp

{{-- Three states, not two. --}}
<span class="inline-flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
    @if ($open === null)
        <span class="text-muted">{{ __('rates.hours_unknown') }}</span>
    @else
        <span @class(['font-semibold', 'text-primary' => $open, 'text-accent-red' => ! $open])>
            {{ $open ? __('rates.open') : __('rates.closed') }}
        </span>
    @endif
</span>
