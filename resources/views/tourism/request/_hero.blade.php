@php
    use App\Support\TravelHero;

    $photo = TravelHero::asset('hero-mobile');
    $showScript = app()->getLocale() !== 'hy';

    $benefits = [
        ['icon' => 'check', 'title' => 'benefit_trusted', 'sub' => 'benefit_trusted_sub'],
        ['icon' => 'sell', 'title' => 'benefit_value', 'sub' => 'benefit_value_sub'],
        ['icon' => 'clock', 'title' => 'benefit_time', 'sub' => 'benefit_time_sub'],
    ];
@endphp
<section class="relative overflow-hidden border-b border-placeholder bg-gradient-to-b from-travel-50/40 via-[#F9FBF6] to-transparent pt-10 pb-16">
    <div class="travel-container">
        <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-12">
            {{-- Copy --}}
            <div class="space-y-6 lg:col-span-7">
                <span class="inline-flex items-center gap-2 rounded-full border border-travel-200 bg-travel-100/70 px-3 py-1 text-xs font-bold tracking-wider text-travel-700 uppercase">
                    <svg class="h-3.5 w-3.5" viewBox="40 39 56 50" fill="none" aria-hidden="true">
                        <path d="M59 56l29-14c4-2 8 2 5 6L84 62l7 19c1 3-2 5-5 4L71 77 58 85c-3 2-6-2-5-5l4-11-11-3c-3-1-3-5 0-6l13-4z" fill="currentColor"/>
                    </svg>
                    {{ __('tourism.request.eyebrow') }}
                </span>
                <h1 class="text-4xl leading-[1.12] font-extrabold tracking-tight text-travel-ink sm:text-5xl lg:text-6xl">
                    {{ __('tourism.request.hero_line1') }}<br>
                    {{ __('tourism.request.hero_line2') }} <span class="inline-block text-travel-600">{{ __('tourism.request.hero_line2_accent') }}</span>
                </h1>

                <p class="max-w-xl text-base leading-relaxed text-gray-600 sm:text-lg">
                    {{ __('tourism.request.subheading') }}
                </p>
                <ul class="grid grid-cols-1 gap-4 pt-4 sm:grid-cols-3">
                    @foreach ($benefits as $signal)
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-travel-100/70 text-travel-700">
                                <x-travel-icon :name="$signal['icon']" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-xs font-bold text-gray-900">{{ __('tourism.request.' . $signal['title']) }}</span>
                                <span class="mt-0.5 block text-[11px] leading-snug text-gray-500">{{ __('tourism.request.' . $signal['sub']) }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if ($photo)
                <div class="relative lg:col-span-5">
                    <div class="relative h-80 w-full overflow-hidden rounded-3xl border-4 border-white shadow-2xl sm:h-96">
                        <picture>
                            @isset($photo['srcset']['avif'])
                                <source type="image/avif" srcset="{{ $photo['srcset']['avif'] }}" sizes="(min-width: 1024px) 40vw, 100vw">
                            @endisset
                            <img
                                src="{{ $photo['src'] }}"
                                alt="{{ __('tourism.request.hero_image_alt') }}"
                                width="{{ $photo['width'] }}"
                                height="{{ $photo['height'] }}"
                                fetchpriority="high"
                                decoding="async"
                                class="h-full w-full object-cover object-[50%_62%]"
                            >
                        </picture>

                        @if ($showScript)
                            <div class="pointer-events-none absolute top-5 left-5 select-none" aria-hidden="true">
                                <p class="block max-w-[11ch] -rotate-3 font-script text-3xl leading-tight font-bold text-sky-900 drop-shadow-sm sm:text-4xl">
                                    {{ __('tourism.request.hero_script') }}
                                </p>
                                <svg class="-mt-1 ml-6 h-10 w-28 fill-none stroke-current text-travel-600/80" viewBox="0 0 100 40" stroke-dasharray="3 3">
                                    <path d="M5,35 Q 50,5 90,15" stroke-width="2" />
                                    <polygon points="90,15 84,10 86,18" fill="currentColor" stroke="none" />
                                </svg>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
