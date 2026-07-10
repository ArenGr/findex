@props(['placement'])

@php
    $ad = \App\Models\Ad::query()
        ->forPlacement($placement)
        ->active()
        ->orderBy('sort_order')
        ->first();
@endphp

@if ($ad)
    <div
        x-data="{ dismissed: sessionStorage.getItem('ad-dismissed-{{ $ad->id }}') === '1' }"
        x-show="!dismissed"
        x-cloak
        class="mt-10 flex justify-center lg:mt-0 lg:block lg:shrink-0 {{ $ad->side->value === 'left' ? 'lg:order-first' : '' }}"
    >
        <div class="lg:sticky lg:top-24">
            <x-ad-banner :ad="$ad" />
        </div>
    </div>
@endif
