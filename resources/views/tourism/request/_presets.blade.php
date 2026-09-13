@php
    $countryFlag = fn (string $code) => collect($countries)->firstWhere('code', $code)['flag'] ?? '';

    // The three facts under each destination.
    $tags = fn (array $preset) => [
        ['icon' => 'calendar_month', 'label' => __('tourism.presets.nights', ['count' => $preset['nights']])],
        ['icon' => 'hotel', 'label' => __('tourism.hotel_class.'.$preset['hotel'])],
        ['icon' => 'restaurant', 'label' => __('tourism.meals.'.$preset['meals'])],
    ];
@endphp

@if (! empty($presets))
    {{-- Between the hero's rule and the form, because it is the shortest way through that form. --}}
    <section
        class="travel-container py-14"
        aria-labelledby="presets-heading"
        :class="{ 'hidden': step !== 1 }"
        @class(['hidden' => $initialStep !== 1])
    >
        {{-- Heading left, the way out to the full list right. --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-xl">
                <h2 id="presets-heading" class="text-2xl font-extrabold tracking-tight text-travel-ink lg:text-[1.75rem]">
                    {{ __('tourism.presets.heading') }}
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ __('tourism.presets.sub') }}</p>
            </div>

            <a
                href="#travel-form-top"
                class="inline-flex shrink-0 items-center gap-1.5 text-sm font-bold text-travel-600 transition-colors hover:text-travel-700"
            >
                {{ __('tourism.presets.view_all') }}
                <x-travel-icon name="arrow_forward" class="h-4 w-4" />
            </a>
        </div>

        {{-- Four across from lg, which is the whole set on one screen. --}}
        <div
            x-data="{
                active: 0,
                total: {{ count($presets) }},
                go(to) {
                    this.active = Math.min(Math.max(to, 0), this.total - 1);
                    this.$refs.track.children[this.active]?.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
                },
                sync() {
                    const card = this.$refs.track.children[0];
                    if (!card) return;
                    const step = card.getBoundingClientRect().width + 20;
                    this.active = Math.round(this.$refs.track.scrollLeft / step);
                },
            }"
            class="relative mt-8"
        >
            <div
                x-ref="track"
                @scroll.debounce.100ms="sync()"
                class="-mx-4 flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pb-2 [-ms-overflow-style:none] [scrollbar-width:none] sm:mx-0 sm:px-0 lg:grid lg:grid-cols-4 lg:overflow-visible [&::-webkit-scrollbar]:hidden"
            >
                @foreach ($presets as $preset)
                    <button
                        type="button"
                        @click="applyPreset(@js($preset + ['departure' => __('tourism.request.departure_default')]))"
                        :class="preset === @js($preset['key']) && 'border-travel-600 ring-2 ring-travel-600/20'"
                        class="group relative flex w-[78%] shrink-0 snap-start flex-col items-start rounded-2xl border border-gray-200 bg-white p-3 pb-16 text-left shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg sm:w-[46%] lg:w-auto"
                    >
                        <span class="relative block h-40 w-full overflow-hidden rounded-xl bg-travel-50">
                            @if ($preset['photo'])
                                <picture>
                                    @isset($preset['photo']['srcset']['avif'])
                                        <source type="image/avif" srcset="{{ $preset['photo']['srcset']['avif'] }}" sizes="(min-width: 1024px) 300px, 80vw">
                                    @endisset
                                    <source type="image/webp" srcset="{{ $preset['photo']['srcset']['webp'] }}" sizes="(min-width: 1024px) 300px, 80vw">
                                    <img
                                        src="{{ $preset['photo']['src'] }}"
                                        alt="{{ $preset['title'] }}"
                                        width="{{ $preset['photo']['width'] }}"
                                        height="{{ $preset['photo']['height'] }}"
                                        loading="lazy"
                                        decoding="async"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    >
                                </picture>
                            @else
                                <span class="flex h-full w-full items-center justify-center text-6xl leading-none" aria-hidden="true">
                                    <span class="transition duration-300 group-hover:scale-105">{{ $countryFlag($preset['country']) }}</span>
                                </span>
                            @endif

                            {{-- The city, on the picture. --}}
                            <span class="absolute top-2.5 right-2.5 inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-travel-ink shadow-sm backdrop-blur-sm">
                                <x-travel-icon name="location_on" class="h-3 w-3 text-travel-600" />
                                {{ $preset['city_label'] }}
                            </span>
                        </span>

                        <span class="mt-4 block w-full px-1">
                            <span class="block text-[15px] font-bold text-travel-ink">{{ $preset['title'] }}</span>

                            @if ($preset['typical_price'])
                                <span class="mt-1 block text-[13px] font-semibold text-travel-600">
                                    {{ __('tourism.presets.from', ['amount' => number_format($preset['typical_price']).' '.__('tourism.request.amd')]) }}
                                </span>
                            @endif
                        </span>

                        <span class="mt-3 flex w-full flex-wrap gap-1 px-1">
                            @foreach ($tags($preset) as $tag)
                                <span class="inline-flex items-center gap-1 rounded-full bg-travel-50 px-2 py-1 text-[10.5px] font-medium whitespace-nowrap text-travel-800">
                                    <x-travel-icon :name="$tag['icon']" class="h-2.5 w-2.5 text-travel-600" />
                                    {{ $tag['label'] }}
                                </span>
                            @endforeach
                        </span>

                        {{-- What the card leaves out, for anyone who cannot see the photograph or the chips. --}}
                        <span class="sr-only">{{ $preset['summary'] }} {{ __('tourism.presets.choose') }}</span>
                        <span class="absolute right-4 bottom-4 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-travel-600 text-white transition-colors duration-300 group-hover:bg-travel-700">
                            <x-travel-icon name="arrow_forward" class="h-4 w-4" />
                        </span>
                    </button>
                @endforeach
            </div>

            {{-- Only while the row scrolls. --}}
            <div class="mt-5 flex items-center justify-center gap-3 lg:hidden">
                <button
                    type="button"
                    @click="go(active - 1)"
                    :disabled="active === 0"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-travel-200 bg-white text-travel-700 transition hover:border-travel-500 disabled:opacity-30 disabled:hover:border-travel-200"
                    aria-label="{{ __('tourism.presets.previous') }}"
                >
                    <x-travel-icon name="arrow_back" class="h-4 w-4" />
                </button>

                <div class="flex items-center gap-2">
                @foreach ($presets as $i => $preset)
                    <button
                        type="button"
                        @click="go({{ $i }})"
                        :class="{ 'bg-travel-600 w-6': active === {{ $i }}, 'bg-border-muted w-2': active !== {{ $i }} }"
                        class="h-2 rounded-full transition-all {{ $i === 0 ? 'bg-travel-600 w-6' : 'bg-border-muted w-2' }}"
                        aria-label="{{ $preset['title'] }}"
                    ></button>
                @endforeach
                </div>

                <button
                    type="button"
                    @click="go(active + 1)"
                    :disabled="active >= total - 1"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-travel-200 bg-white text-travel-700 transition hover:border-travel-500 disabled:opacity-30 disabled:hover:border-travel-200"
                    aria-label="{{ __('tourism.presets.next') }}"
                >
                    <x-travel-icon name="arrow_forward" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </section>
@endif
