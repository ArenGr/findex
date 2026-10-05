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
                <h2 id="presets-heading" class="text-2xl font-extrabold tracking-tight text-ink lg:text-[1.75rem]">
                    {{ __('tourism.presets.heading') }}
                </h2>
                <p class="mt-2 text-sm leading-relaxed text-muted">{{ __('tourism.presets.sub') }}</p>
            </div>

            <a
                href="#travel-form-top"
                class="inline-flex shrink-0 items-center gap-1.5 text-sm font-bold text-primary transition-colors hover:text-primary-dark"
            >
                {{ __('tourism.presets.view_all') }}
                <x-travel-icon name="arrow_forward" class="h-4 w-4" />
            </a>
        </div>

        {{-- One list, one row per trip: the words do the work, so no photographs. --}}
        <ul class="mt-8 divide-y divide-border overflow-hidden rounded-2xl border border-border bg-surface">
            @foreach ($presets as $preset)
                <li>
                    <button
                        type="button"
                        @click="applyPreset(@js($preset + ['departure' => __('tourism.request.departure_default')]))"
                        :class="preset === @js($preset['key']) ? 'bg-primary/5' : ''"
                        class="group flex w-full flex-col gap-3 px-5 py-4 text-left transition hover:bg-surface-alt focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none sm:flex-row sm:items-center sm:gap-5"
                    >
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-surface-alt text-xl leading-none" aria-hidden="true">
                            {{ $countryFlag($preset['country']) }}
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-[15px] font-bold text-ink">{{ $preset['title'] }}</span>

                            <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                                <span class="inline-flex items-center gap-1">
                                    <x-travel-icon name="location_on" class="h-3.5 w-3.5 text-primary" />
                                    {{ $preset['city_label'] }}
                                </span>

                                @foreach ($tags($preset) as $tag)
                                    <span class="inline-flex items-center gap-1">
                                        <x-travel-icon :name="$tag['icon']" class="h-3.5 w-3.5 text-primary" />
                                        {{ $tag['label'] }}
                                    </span>
                                @endforeach
                            </span>
                        </span>

                        @if ($preset['typical_price'])
                            <span class="shrink-0 text-sm font-semibold whitespace-nowrap text-primary sm:text-right">
                                {{ __('tourism.presets.from', ['amount' => number_format($preset['typical_price']).' '.__('tourism.request.amd')]) }}
                            </span>
                        @endif

                        {{-- What the row leaves out, for anyone who cannot see the chips. --}}
                        <span class="sr-only">{{ $preset['summary'] }} {{ __('tourism.presets.choose') }}</span>

                        <x-travel-icon name="arrow_forward" class="hidden h-4 w-4 shrink-0 text-subtle transition-colors group-hover:text-primary sm:block" />
                    </button>
                </li>
            @endforeach
        </ul>
    </section>
@endif
