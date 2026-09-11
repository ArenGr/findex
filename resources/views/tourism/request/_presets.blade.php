@php
    use Illuminate\Support\Carbon;

    // The flag the destination chips already use, so a preset's country reads
    // the same here as it does once it is applied.
    $countryFlag = fn (string $code) => collect($countries)->firstWhere('code', $code)['flag'] ?? '';

    $dateRange = fn (array $preset) => Carbon::parse($preset['check_in'])->isoFormat('D MMM')
        .' – '.Carbon::parse($preset['check_out'])->isoFormat('D MMM');
@endphp

@if (! empty($presets))
    {{-- Between the hero's rule and the form, because it is the shortest way
         through that form.

         Choosing one fills every trip field and lands on "Review & send", so
         what is left is the contact details a guest has to give whichever way
         they got there. Nothing is locked: the request is there to be read and
         changed before it goes.

         Hidden once the traveller is past step 1 - the hero stays on every
         step, and a row of one-click presets sitting over a half-answered
         request is an invitation to throw it away by accident. Object form and
         a server-rendered match, so the right state paints in the first frame
         rather than after Alpine boots. --}}
    <section
        class="travel-container py-14"
        aria-labelledby="presets-heading"
        :class="{ 'hidden': step !== 1 }"
        @class(['hidden' => $initialStep !== 1])
    >
        {{-- Centred heading block, like every other card section on the site. --}}
        <div class="mx-auto max-w-2xl text-center">
            <span class="inline-flex items-center rounded-full bg-primary/10 px-4 py-1.5 text-xs font-semibold tracking-wide text-primary uppercase">
                {{ __('tourism.presets.eyebrow') }}
            </span>
            <h2 id="presets-heading" class="mt-4 font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('tourism.presets.heading') }}</h2>
            <p class="mt-3 text-sm leading-relaxed text-muted">{{ __('tourism.presets.sub') }}</p>
        </div>

        {{-- The home page's service cards, in the same carousel: below sm a
             native scroll-snap row, one card per swipe, with the browser's own
             momentum scrolling; a plain grid from sm up. See
             x-services-grid, which this deliberately mirrors rather than
             inventing a second card shape for the same site. --}}
        <div
            x-data="{
                active: 0,
                total: {{ count($presets) }},
                scrollToActive() {
                    this.$refs.track.children[this.active]?.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                },
            }"
            class="mt-12"
        >
            <div
                x-ref="track"
                @scroll.debounce.100ms="active = Math.round($el.scrollLeft / $el.clientWidth)"
                class="-mx-4 flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pb-2 [-ms-overflow-style:none] [scrollbar-width:none] sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-6 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-4 [&::-webkit-scrollbar]:hidden"
            >
                @foreach ($presets as $preset)
                    <button
                        type="button"
                        @click="applyPreset(@js($preset + ['departure' => __('tourism.request.departure_default')]))"
                        :class="preset === @js($preset['key']) && 'border-primary ring-2 ring-primary/20'"
                        class="group relative flex w-full shrink-0 snap-center flex-col items-center gap-5 rounded-2xl border border-travel-200 bg-white px-6 pt-8 pb-14 text-center shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg sm:w-auto sm:shrink"
                    >
                        {{-- The destination's flag where the services grid puts
                             its illustration. There is no photograph per preset
                             and a stock one would be a picture of somewhere the
                             agencies have not quoted yet. --}}
                        <span class="flex h-32 w-full items-center justify-center rounded-2xl bg-travel-50 text-6xl leading-none" aria-hidden="true">
                            <span class="transition duration-300 group-hover:scale-105">{{ $countryFlag($preset['country']) }}</span>
                        </span>

                        <span class="block">
                            <span class="block font-semibold text-ink">{{ $preset['title'] }}</span>
                            <span class="mt-1.5 block text-xs leading-relaxed text-muted">{{ $preset['summary'] }}</span>
                        </span>

                        <span class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[11px] text-muted">
                            <span class="inline-flex items-center gap-1.5">
                                <x-travel-icon name="calendar_month" class="h-3.5 w-3.5 text-primary" />
                                {{ $dateRange($preset) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <x-travel-icon name="hotel" class="h-3.5 w-3.5 text-primary" />
                                {{ __('tourism.presets.nights', ['count' => $preset['nights']]) }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <x-travel-icon name="group" class="h-3.5 w-3.5 text-primary" />
                                {{ __('tourism.presets.travellers', ['count' => $preset['adults']]) }}
                            </span>
                        </span>

                        @if ($preset['typical_price'])
                            {{-- Only when enough agencies have actually answered
                                 for this destination - see
                                 QuoteRequestController::typicalPrices(). --}}
                            <span class="absolute top-4 left-4 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-bold text-primary shadow-sm">
                                {{ __('tourism.presets.from', ['amount' => number_format($preset['typical_price']).' '.__('tourism.request.amd')]) }}
                            </span>
                        @endif

                        <span class="sr-only">{{ __('tourism.presets.choose') }}</span>
                        <span class="absolute right-4 bottom-4 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-primary bg-primary text-white transition-colors duration-300 group-hover:bg-white group-hover:text-primary">
                            <x-travel-icon name="arrow_forward" class="h-3 w-3" />
                        </span>
                    </button>
                @endforeach
            </div>

            {{-- Swipe position dots - mobile only, the sm:grid above needs no
                 page indicator. --}}
            <div class="mt-4 flex items-center justify-center gap-2 sm:hidden">
                @foreach ($presets as $i => $preset)
                    {{-- The width lives in the class attribute as well as the
                         binding, or every dot paints at zero width and pops out
                         to 2/6px when Alpine boots. Object form, not a ternary:
                         a ternary only clears what Alpine itself added, so the
                         width rendered here would never come off. --}}
                    <button
                        type="button"
                        @click="active = {{ $i }}; scrollToActive()"
                        :class="{ 'bg-primary w-6': active === {{ $i }}, 'bg-border-muted w-2': active !== {{ $i }} }"
                        class="h-2 rounded-full transition-all {{ $i === 0 ? 'bg-primary w-6' : 'bg-border-muted w-2' }}"
                        aria-label="{{ __('hero.go_to_slide', ['n' => $i + 1]) }}"
                    ></button>
                @endforeach
            </div>
        </div>
    </section>
@endif
