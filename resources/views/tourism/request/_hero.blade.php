{{-- The travel hero. --}}
@php
    use App\Support\TravelHero;

    $photo = TravelHero::asset('hero-mobile');

    $showScript = app()->getLocale() !== 'hy';

    $benefits = [
        ['icon' => 'shield_check', 'title' => 'benefit_trusted', 'sub' => 'benefit_trusted_sub'],
        ['icon' => 'sell', 'title' => 'benefit_value', 'sub' => 'benefit_value_sub'],
        ['icon' => 'clock', 'title' => 'benefit_time', 'sub' => 'benefit_time_sub'],
    ];
@endphp

<section class="relative isolate overflow-hidden border-b border-placeholder bg-sky-50">
    @if ($photo)
        {{-- The picture is the band's own background, full bleed. --}}
        <div class="absolute inset-0 -z-10">
            <picture>
                @isset($photo['srcset']['avif'])
                    <source type="image/avif" srcset="{{ $photo['srcset']['avif'] }}" sizes="100vw">
                @endisset
                <source type="image/webp" srcset="{{ $photo['srcset']['webp'] }}" sizes="100vw">
                <img
                    src="{{ $photo['src'] }}"
                    alt="{{ __('tourism.request.hero_image_alt') }}"
                    width="{{ $photo['width'] }}"
                    height="{{ $photo['height'] }}"
                    fetchpriority="high"
                    decoding="async"
                    class="h-full w-full object-cover object-[62%_42%]"
                >
            </picture>

            {{-- Two passes. --}}
            <div class="absolute inset-0 bg-white/75 lg:hidden" aria-hidden="true"></div>
            <div
                class="absolute inset-0 hidden lg:block"
                style="background: linear-gradient(90deg, #F2F9FE 0%, #F2F9FE 30%, rgba(242,249,254,0.86) 42%, rgba(242,249,254,0.32) 54%, rgba(242,249,254,0) 65%)"
                aria-hidden="true"
            ></div>
        </div>
    @endif

    <div class="travel-container relative py-12 lg:py-16">
        <div class="max-w-xl lg:max-w-[46%]">
            <span class="inline-flex items-center gap-2 rounded-full border border-travel-200 bg-white/80 px-3 py-1 text-[11px] font-bold tracking-wider text-travel-700 uppercase backdrop-blur-sm">
                {{-- The paper plane lifted from the pack's travel-offers-badge, cropped to the glyph's own bounds. --}}
                <svg class="h-3.5 w-3.5" viewBox="40 39 56 50" fill="none" aria-hidden="true">
                    <path d="M59 56l29-14c4-2 8 2 5 6L84 62l7 19c1 3-2 5-5 4L71 77 58 85c-3 2-6-2-5-5l4-11-11-3c-3-1-3-5 0-6l13-4z" fill="currentColor"/>
                </svg>
                {{ __('tourism.request.eyebrow') }}
            </span>

            {{-- Two lines, the second carrying the brand green. --}}
            <h1 class="mt-4 text-[2.1rem] leading-[1.1] font-extrabold tracking-tight text-travel-ink sm:text-5xl">
                {{ __('tourism.request.hero_line1') }}<br>
                <span class="text-travel-600">{{ __('tourism.request.hero_line2') }} {{ __('tourism.request.hero_line2_accent') }}</span>
            </h1>

            <p class="mt-4 max-w-md text-[15px] leading-relaxed text-gray-600">
                {{ __('tourism.request.subheading') }}
            </p>

        </div>

        <div class="lg:max-w-[62%]">
            <ul class="mt-7 flex flex-wrap items-center gap-x-8 gap-y-4">
                @foreach ($benefits as $signal)
                    <li class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-travel-600 shadow-sm">
                            <x-travel-icon :name="$signal['icon']" class="h-[17px] w-[17px]" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-[12.5px] leading-4 font-bold text-travel-ink">{{ __('tourism.request.' . $signal['title']) }}</span>
                            <span class="mt-0.5 block text-[11px] leading-tight text-gray-500">{{ __('tourism.request.' . $signal['sub']) }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    @if ($photo && $showScript)
        {{-- Over the open sky, right of the copy column. --}}
        <div class="pointer-events-none absolute top-14 left-[53%] hidden w-[15rem] -rotate-3 select-none lg:block" aria-hidden="true">
            <p class="font-script text-[2rem] leading-tight font-bold text-travel-ink xl:text-[2.4rem]">
                {{ __('tourism.request.hero_script') }}
            </p>
            {{-- The swash under the line, drawn rather than typed so it holds its weight at any size. --}}
            <svg class="mt-1 h-3 w-36 fill-none stroke-travel-600" viewBox="0 0 150 14" aria-hidden="true">
                <path d="M2 10C28 3 78 1 148 5" stroke-width="3" stroke-linecap="round" />
            </svg>
        </div>
    @endif

    @if ($photo)
        {{-- Says where the photograph actually is. --}}
        <p class="absolute right-4 bottom-4 inline-flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-[11px] font-semibold text-white backdrop-blur-sm lg:right-8">
            <x-travel-icon name="location_on" class="h-3.5 w-3.5" />
            {{ __('tourism.request.hero_photo_place') }}
        </p>
    @endif
</section>
