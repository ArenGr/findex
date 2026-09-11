{{--
    The closing band.

    A photograph held inside the page's own column as a rounded card, with the
    pitch floated over it. It used to bleed to both edges of the viewport,
    which made it the only element on the page that ignored the column every
    other section lines up on.
--}}
@php
    use App\Support\TravelHero;

    $panorama = TravelHero::asset('panorama');
    $showScript = app()->getLocale() !== 'hy';
@endphp

@if ($panorama)
    <section class="travel-container pb-20">
        {{-- bg-travel-100: the photograph below is lazy-loaded, so the card
             resolves out of a soft green rather than out of nothing. --}}
        <div class="relative flex min-h-[380px] items-center overflow-hidden rounded-3xl bg-travel-100 shadow-2xl">
            <picture>
                @isset($panorama['srcset']['avif'])
                    <source type="image/avif" srcset="{{ $panorama['srcset']['avif'] }}" sizes="(min-width: 1280px) 1216px, 100vw">
                @endisset
                <source type="image/webp" srcset="{{ $panorama['srcset']['webp'] }}" sizes="(min-width: 1280px) 1216px, 100vw">
                <img
                    src="{{ $panorama['src'] }}"
                    alt="{{ __('tourism.request.closing_image_alt') }}"
                    width="{{ $panorama['width'] }}"
                    height="{{ $panorama['height'] }}"
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 h-full w-full object-cover object-center"
                >
            </picture>

            {{-- Darkens the two edges and leaves the middle alone: the card
                 sits on the left and the script line on the right, and both
                 need something to hold contrast against. --}}
            <div class="absolute inset-0 bg-gradient-to-r from-black/40 via-transparent to-black/20" aria-hidden="true"></div>

            <div class="relative z-10 m-6 max-w-md space-y-4 rounded-2xl border border-white/60 bg-white/95 p-8 shadow-xl backdrop-blur-md sm:m-12 sm:p-10">
                <p class="text-xs font-bold tracking-wide text-gray-500 uppercase">{{ __('tourism.request.closing_eyebrow') }}</p>
                <h2 class="text-2xl leading-snug font-extrabold tracking-tight text-travel-ink sm:text-3xl">
                    {{ __('tourism.request.closing_title_1') }}<br>
                    {{ __('tourism.request.closing_title_2') }}
                </h2>
                <p class="text-xs leading-relaxed text-gray-600 sm:text-sm">{{ __('tourism.request.closing_body') }}</p>

                {{-- The band closes the page's argument and had nothing to do
                     at the end of it. The form is a long way back up by this
                     point, so this is the way back to it. --}}
                <div class="pt-2">
                    <a href="#travel-form-top" class="inline-flex items-center gap-2 rounded-lg bg-travel-600 px-5 py-3 text-xs font-bold text-white shadow transition-all hover:bg-travel-700 sm:text-sm">
                        {{ __('tourism.request.heading') }}
                        <x-travel-icon name="arrow_forward" class="h-4 w-4" />
                    </a>
                </div>
            </div>

            @if ($showScript)
                {{-- Absolutely positioned, so a late Caveat cannot move
                     anything. White over the darkened right edge. --}}
                <p
                    class="pointer-events-none absolute right-16 bottom-12 z-10 hidden max-w-[11ch] -rotate-2 text-right font-script text-5xl text-white/90 drop-shadow-md select-none lg:block"
                    aria-hidden="true"
                >{{ __('tourism.request.closing_script') }}</p>
            @endif
        </div>
    </section>
@endif
