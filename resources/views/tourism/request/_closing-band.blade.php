{{-- The closing band. --}}
@php
    use App\Support\TravelHero;

    $panorama = TravelHero::asset('panorama');
    $showScript = app()->getLocale() !== 'hy';
@endphp

@if ($panorama)
    <section class="travel-container pb-20">
        <div class="grid overflow-hidden rounded-3xl shadow-2xl lg:grid-cols-2">
            <div class="flex flex-col justify-center gap-4 bg-travel-700 p-8 sm:p-12 lg:p-14">
                <p class="text-xs font-bold tracking-wider text-travel-100 uppercase">{{ __('tourism.request.closing_eyebrow') }}</p>
                <h2 class="text-2xl leading-snug font-extrabold tracking-tight text-white sm:text-3xl">
                    {{ __('tourism.request.closing_title_1') }}<br>
                    {{ __('tourism.request.closing_title_2') }}
                </h2>
                <p class="max-w-md text-sm leading-relaxed text-travel-50/90">{{ __('tourism.request.closing_body') }}</p>

                {{-- The band closes the page's argument and had nothing to do at the end of it. --}}
                <div class="pt-2">
                    <a href="#travel-form-top" class="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-bold text-travel-700 shadow transition-colors hover:bg-travel-50">
                        {{ __('tourism.request.heading') }}
                        <x-travel-icon name="arrow_forward" class="h-4 w-4" />
                    </a>
                </div>
            </div>

            <div class="relative min-h-[260px] bg-travel-100 lg:min-h-[380px]">
                <picture>
                    @isset($panorama['srcset']['avif'])
                        <source type="image/avif" srcset="{{ $panorama['srcset']['avif'] }}" sizes="(min-width: 1024px) 608px, 100vw">
                    @endisset
                    <source type="image/webp" srcset="{{ $panorama['srcset']['webp'] }}" sizes="(min-width: 1024px) 608px, 100vw">
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

                @if ($showScript)
                    {{-- Absolutely positioned, so a late Caveat cannot move anything. --}}
                    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/50 to-transparent" aria-hidden="true"></div>
                    <p
                        class="pointer-events-none absolute right-8 bottom-8 hidden max-w-[11ch] -rotate-2 text-right font-script text-4xl leading-tight text-white drop-shadow-md select-none sm:block xl:text-5xl"
                        aria-hidden="true"
                    >{{ __('tourism.request.closing_script') }}</p>
                @endif
            </div>
        </div>
    </section>
@endif
