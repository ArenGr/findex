{{--
    The travel hero.

    The photograph is its own layer, and it owns its shape. That matters
    because hero-source.png bakes a cream blob into its left third - that blob
    was the "giant pale circle" sitting over the copy, and no CSS was drawing
    it. hero-photo-source.png is cut from past it (see
    tools/build-travel-assets.mjs), so the boundary here is an SVG mask we
    control rather than one the asset dictates.

    Three things are tuned independently, which is the only way this
    composition comes out right:

      size   the wrapper's width (~56% of the hero)
      crop   object-position inside that wrapper
      shape  the clip path on the wrapper

    The wrapper also owns everything that has to sit ON the photograph - the
    handwriting, the caption chip, the flight path - so they are positioned
    against the image rather than against the viewport, and they travel with it
    when the crop changes.

    Desktop is the priority: the mask applies from lg up. Below that the
    photograph is a plain rounded band above the copy.
--}}
@php
    use App\Support\TravelHero;

    $photo = TravelHero::asset('hero-photo');
    $photoMobile = TravelHero::asset('hero-mobile');

    // Caveat has no Armenian coverage, and a handwritten line set in a
    // non-handwritten face reads as a bug rather than a flourish.
    $showScript = app()->getLocale() !== 'hy';

    $benefits = [
        ['icon' => 'feature-trusted', 'title' => 'benefit_trusted', 'sub' => 'benefit_trusted_sub'],
        ['icon' => 'feature-value', 'title' => 'benefit_value', 'sub' => 'benefit_value_sub'],
        ['icon' => 'feature-time', 'title' => 'benefit_time', 'sub' => 'benefit_time_sub'],
    ];
@endphp

<section class="relative overflow-hidden bg-travel-cream lg:h-[380px] 2xl:h-[420px]">
    {{-- Decorative, behind everything, clipped by the section. --}}
    <img
        src="{{ asset('images/travel/svg/bg-leaf-left.svg') }}"
        alt=""
        aria-hidden="true"
        width="600"
        height="400"
        class="pointer-events-none absolute -bottom-24 -left-28 z-0 w-[380px] opacity-30 select-none"
    >

    {{-- Mobile: the portrait crop, stacked above the copy. --}}
    <div class="relative mx-4 mt-4 h-52 overflow-hidden rounded-2xl sm:h-64 lg:hidden">
        @if ($photoMobile)
            <picture>
                @isset($photoMobile['srcset']['avif'])
                    <source type="image/avif" srcset="{{ $photoMobile['srcset']['avif'] }}" sizes="100vw">
                @endisset
                <source type="image/webp" srcset="{{ $photoMobile['srcset']['webp'] }}" sizes="100vw">
                <img
                    src="{{ $photoMobile['src'] }}"
                    alt="{{ __('tourism.request.hero_image_alt') }}"
                    width="{{ $photoMobile['width'] }}"
                    height="{{ $photoMobile['height'] }}"
                    sizes="100vw"
                    fetchpriority="high"
                    decoding="async"
                    class="h-full w-full object-cover"
                >
            </picture>
        @endif
    </div>

    @if ($photo)
        {{-- The photograph is the hero's background, full bleed, covering the
             whole band - not a column on the right. The text area is made by
             the overlay above it rather than by taking the image away, so the
             copy sits on near-white while the picture stays faintly present
             underneath it and resolves as the eye travels right. --}}
        <div class="pointer-events-none absolute inset-0 z-0 hidden lg:block">
            <picture>
                @isset($photo['srcset']['avif'])
                    <source type="image/avif" srcset="{{ $photo['srcset']['avif'] }}" sizes="100vw">
                @endisset
                <source type="image/webp" srcset="{{ $photo['srcset']['webp'] }}" sizes="100vw">
                {{-- The band is far wider than the picture is tall, so cover
                     trims vertically; 42% holds the waterline and the village
                     roofs rather than centring on empty sky. --}}
                <img
                    src="{{ $photo['src'] }}"
                    alt="{{ __('tourism.request.hero_image_alt') }}"
                    width="{{ $photo['width'] }}"
                    height="{{ $photo['height'] }}"
                    sizes="100vw"
                    fetchpriority="high"
                    decoding="async"
                    class="h-full w-full object-cover object-[50%_42%]"
                >
            </picture>

            {{-- The text area.

                 Two layers, not one: the linear pass carries the left-to-right
                 fade, and the ellipse anchored off the left edge bends it, so
                 the boundary reads as a soft organic falloff rather than a
                 vertical band.

                 The stops are taken from the reference itself. Sampling it
                 across the copy column gives flat #F7F8F5 with a local standard
                 deviation of 0.26-1.13 from 10% to 46% of the width - that is
                 no picture under the text at all - then full sea (sd 2+) by
                 58%. So: opaque to 40%, handing over between 40% and 62%. The
                 photograph is still the layer underneath the whole band; the
                 overlay is simply what decides where it starts being read. --}}
            <div
                class="absolute inset-0"
                style="background:
                    radial-gradient(102% 138% at 0% 50%, #F7FAF3 0%, #F7FAF3 34%, rgba(247,250,243,0.78) 44%, rgba(247,250,243,0.26) 54%, rgba(247,250,243,0) 63%),
                    linear-gradient(90deg, #F7FAF3 0%, #F7FAF3 30%, rgba(247,250,243,0.68) 40%, rgba(247,250,243,0.22) 48%, rgba(247,250,243,0) 55%)"
                aria-hidden="true"
            ></div>

            {{-- The flight path. Carried as a vector because the crop cuts the
                 one baked into the photograph. --}}
            <img
                src="{{ asset('images/travel/svg/plane-path.svg') }}"
                alt=""
                aria-hidden="true"
                width="1000"
                height="400"
                class="pointer-events-none absolute top-[4%] left-[56%] w-[26%] opacity-75 select-none"
            >

            @if ($showScript)
                {{-- Over open sky and water, clear of the architecture.
                     Absolutely positioned, so however late Caveat lands it
                     cannot move anything; it is `optional` besides, so it never
                     swaps in mid-view. --}}
                <p
                    class="absolute top-[16%] left-[57%] max-w-[9ch] -rotate-2 font-script text-[30px] leading-[1.16] text-travel-script select-none xl:text-[34px]"
                    aria-hidden="true"
                >{{ __('tourism.request.hero_script') }}</p>
            @endif

            {{-- In the negative space below the village, not over a dome. The
                 wizard covers the band's last ~78px, so this has to clear it. --}}
            <div class="pointer-events-auto absolute right-[4%] bottom-[27%] flex items-center gap-2.5 rounded-xl bg-white/95 px-3.5 py-2.5 shadow-[0_8px_28px_rgba(13,24,42,0.16)]">
                <img src="{{ asset('images/travel/svg/icon-location.svg') }}" alt="" aria-hidden="true" width="200" height="200" class="h-[18px] w-[18px]">
                <span class="leading-tight">
                    <span class="block text-[12.5px] font-semibold text-travel-ink">{{ __('tourism.request.hero_badge_title') }}</span>
                    <span class="block text-[11.5px] text-travel-muted">{{ __('tourism.request.hero_badge_sub') }}</span>
                </span>
            </div>
        </div>
    @endif

    <div class="travel-container relative z-10 h-full">
        <div class="py-9 lg:w-[44%] lg:pt-6 lg:pb-0">
            <span class="inline-flex h-7 items-center gap-1.5 rounded-full bg-travel-sage px-3 text-[11px] font-bold tracking-[0.07em] text-travel-green uppercase">
                {{-- The paper plane lifted from the pack's travel-offers-badge,
                     cropped to the glyph's own bounds. Its label stays as text
                     so it translates. --}}
                <svg class="h-3.5 w-3.5" viewBox="40 39 56 50" fill="none" aria-hidden="true">
                    <path d="M59 56l29-14c4-2 8 2 5 6L84 62l7 19c1 3-2 5-5 4L71 77 58 85c-3 2-6-2-5-5l4-11-11-3c-3-1-3-5 0-6l13-4z" fill="currentColor"/>
                </svg>
                {{ __('tourism.request.eyebrow') }}
            </span>

            {{-- Two lines, with the closing word carrying the brand green. The
                 parts are separate strings because no two languages break the
                 phrase in the same place. --}}
            <h1 class="mt-3 font-heading text-[2.25rem] leading-[0.97] font-bold tracking-[-0.035em] text-travel-ink sm:text-[3rem] lg:text-[3.6rem] 2xl:text-[4.1rem]">
                {{ __('tourism.request.hero_line1') }}<br>
                {{ __('tourism.request.hero_line2') }} <span class="text-travel-green">{{ __('tourism.request.hero_line2_accent') }}</span>
            </h1>

            <p class="mt-3 max-w-[25rem] text-[15px] leading-6 text-travel-muted">
                {{ __('tourism.request.subheading') }}
            </p>

            {{-- Each signal answers "why here" in two beats: the claim, then
                 what backs it. A fixed three-up grid rather than a wrapping
                 row, so the longest sub-line wraps inside its own column
                 instead of moving the whole row in one locale and not another. --}}
            <ul class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3 sm:gap-x-3">
                @foreach ($benefits as $signal)
                    <li class="flex items-center gap-2.5">
                        <img
                            src="{{ asset('images/travel/svg/' . $signal['icon'] . '.svg') }}"
                            alt=""
                            aria-hidden="true"
                            width="200"
                            height="200"
                            class="h-8 w-8 shrink-0"
                        >
                        <span class="min-w-0">
                            <span class="block text-[12.5px] leading-4 font-semibold text-travel-ink">{{ __('tourism.request.' . $signal['title']) }}</span>
                            <span class="mt-0.5 block text-[11px] leading-[1.35] text-travel-muted">{{ __('tourism.request.' . $signal['sub']) }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
