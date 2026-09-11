{{--
    The agencies a visitor's request actually goes out to.

    Driven by real partner organizations rather than the design pack's mockup
    logos, which are invented brands. Rendered only when there are enough to
    read as a row - a "trusted by" strip listing one agency argues against
    itself - so this section can be absent on a fresh database and appear on
    its own once partners exist.
--}}
@props(['partners'])

@if ($partners->count() >= \App\Support\TravelPartners::MIN_TO_SHOW)
    {{-- On the page's own white, not in a tinted panel. The tint made a block
         of the strip and set it against everything around it, when all it has
         to do is name the agencies a request goes to. --}}
    <section class="travel-container pb-16">
        <div class="space-y-5 border-t border-gray-100 pt-10">
            <p class="flex items-center gap-2 text-xs font-bold tracking-wider text-travel-700 uppercase">
                <x-travel-icon name="shield_check" class="h-4 w-4" />
                {{ __('tourism.request.partners_heading') }}
            </p>

            {{-- Badges that size to their own name and wrap, not a fixed grid.
                 Seven columns cut every agency to about 170px, which truncated
                 the one name each badge exists to show. --}}
            <ul class="flex flex-wrap items-center gap-3">
                @foreach ($partners as $partner)
                    <li class="flex items-center gap-2.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2.5">
                        @if ($partner->logo)
                            {{-- Sized rather than intrinsic: partner logos
                                 arrive at whatever dimensions they were
                                 uploaded at, and an unsized one would
                                 reflow the row as it decodes. --}}
                            <img
                                src="{{ $partner->logo }}"
                                alt="{{ $partner->name }}"
                                width="28"
                                height="28"
                                loading="lazy"
                                decoding="async"
                                referrerpolicy="no-referrer"
                                class="h-7 w-7 shrink-0 rounded-lg object-contain"
                            >
                        @else
                            {{-- No logo on file: the flow's own travel glyph on
                                 a tinted tile, never a stand-in mark that could
                                 read as the agency's own. --}}
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-travel-50 text-travel-600" aria-hidden="true">
                                <x-travel-icon name="luggage" class="h-4 w-4" />
                            </span>
                        @endif
                        <span class="text-xs font-semibold whitespace-nowrap text-gray-800">{{ $partner->name }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
