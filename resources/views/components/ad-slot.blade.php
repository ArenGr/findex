@props(['placement'])

@php
    $ad = \App\Models\Ad::query()
        ->forPlacement($placement)
        ->active()
        ->orderBy('sort_order')
        ->first();
@endphp

@if ($ad)
    {{-- No x-cloak. This slot sits in a flex row beside the page's main
         column, so hiding it until Alpine booted did not just make the ad
         appear late - it left the column ~340px wider than its final width,
         and every heading and paragraph in it re-wrapped the moment the ad
         showed up. That reads as the whole page resizing its text.

         The server cannot know whether this visitor dismissed the ad, since
         that lives in sessionStorage, so it renders the common case: not
         dismissed. Someone who did dismiss it sees it collapse once on the
         next page of that session, which is both rare and self-inflicted -
         the reverse cost every visitor a reflow on every page. --}}
    <div
        x-data="{ dismissed: sessionStorage.getItem('ad-dismissed-{{ $ad->id }}') === '1' }"
        x-show="!dismissed"
        class="mt-10 flex justify-center lg:mt-0 lg:block lg:shrink-0 {{ $ad->side->value === 'left' ? 'lg:order-first' : '' }}"
    >
        <div class="lg:sticky lg:top-24">
            <x-ad-banner :ad="$ad" />
        </div>
    </div>
@endif
