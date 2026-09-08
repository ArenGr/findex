{{--
    The closing band.

    A full-bleed photograph with the pitch floated over it. The card is frosted
    rather than solid so the mountains stay readable behind it, and the copy
    keeps a solid-enough ground to hold contrast over the brightest part of the
    image.
--}}
@php
    use App\Support\TravelHero;

    $panorama = TravelHero::asset('panorama');
    $showScript = app()->getLocale() !== 'hy';
@endphp

@if ($panorama)
    {{-- bg-travel-sage: the photograph below is lazy-loaded, so the band
         resolves out of a soft green rather than out of nothing. --}}
    <section class="relative isolate h-[300px] overflow-hidden bg-travel-sage lg:h-[320px]">
        <picture>
            @isset($panorama['srcset']['avif'])
                <source type="image/avif" srcset="{{ $panorama['srcset']['avif'] }}" sizes="100vw">
            @endisset
            <source type="image/webp" srcset="{{ $panorama['srcset']['webp'] }}" sizes="100vw">
            <img
                src="{{ $panorama['src'] }}"
                alt="{{ __('tourism.request.closing_image_alt') }}"
                width="{{ $panorama['width'] }}"
                height="{{ $panorama['height'] }}"
                sizes="100vw"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 -z-10 h-full w-full object-cover"
            >
        </picture>

        <div class="travel-container flex h-full items-center py-10">
            <div class="max-w-[27rem] rounded-[20px] border border-white/50 bg-white/70 p-6 shadow-[0_18px_50px_rgba(13,24,42,0.12)] backdrop-blur-md lg:p-7">
                <p class="text-[13px] font-medium text-travel-muted">{{ __('tourism.request.closing_eyebrow') }}</p>
                <h2 class="mt-1.5 font-heading text-[1.55rem] leading-[1.15] font-bold tracking-[-0.02em] text-travel-ink sm:text-[1.85rem]">
                    {{ __('tourism.request.closing_title_1') }}<br>
                    {{ __('tourism.request.closing_title_2') }}
                </h2>
                <p class="mt-3 text-[14px] leading-[1.55] text-travel-ink/80">{{ __('tourism.request.closing_body') }}</p>
            </div>
        </div>

        @if ($showScript)
            {{-- Absolutely positioned, so a late Caveat cannot move anything. --}}
            <p
                class="pointer-events-none absolute right-[6%] bottom-[18%] hidden max-w-[8ch] rotate-[-6deg] font-script text-[30px] leading-[1.05] text-travel-ink select-none lg:block xl:text-[36px]"
                aria-hidden="true"
            >{{ __('tourism.request.closing_script') }}</p>
        @endif
    </section>
@endif
