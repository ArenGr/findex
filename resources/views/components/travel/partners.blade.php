{{-- The agencies a visitor's request actually goes out to. --}}
@props(['partners'])

@if ($partners->count() >= \App\Support\TravelPartners::MIN_TO_SHOW)
    {{-- On the page's own white, not in a tinted panel. --}}
    <section class="travel-container pb-16">
        <div class="space-y-5 border-t border-gray-100 pt-10">
            <p class="flex items-center gap-2 text-xs font-bold tracking-wider text-travel-700 uppercase">
                <x-travel-icon name="shield_check" class="h-4 w-4" />
                {{ __('tourism.request.partners_heading') }}
            </p>

            {{-- Badges that size to their own name and wrap, not a fixed grid. --}}
            <ul class="flex flex-wrap items-center gap-3">
                @foreach ($partners as $partner)
                    <li class="flex items-center gap-2.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2.5">
                        @if ($partner->logo)
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
