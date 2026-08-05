@php
    $pillars = [
        ['label' => __('about.hero.pillars.cards'), 'color' => 'slide-green'],
        ['label' => __('about.hero.pillars.rates'), 'color' => 'slide-blue'],
        ['label' => __('about.hero.pillars.travel'), 'color' => 'accent-blue'],
        ['label' => __('about.hero.pillars.insurance'), 'color' => 'slide-pink'],
    ];

    $stats = [
        ['value' => '+' . \App\Models\Organization::query()->active()->count(), 'label' => __('about.stats.banks_label')],
        ['value' => '+' . \App\Models\Currency::where('is_active', true)->count(), 'label' => __('about.stats.currencies_label')],
        ['value' => __('about.stats.realtime_value'), 'label' => __('about.stats.realtime_label')],
    ];
@endphp

<section class="border-t border-placeholder bg-primary/5">
    <div class="mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-6 py-16 lg:grid-cols-2 lg:px-10">
        {{-- Decorative composition of the four things Findex compares --}}
        <div class="relative mx-auto w-full max-w-sm">
            <div class="absolute -inset-6 -z-10 rounded-[2rem] bg-slide-purple/20"></div>
            <div class="grid grid-cols-2 gap-4">
                @foreach ($pillars as $i => $pillar)
                    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-placeholder/60 {{ $i % 2 === 1 ? 'mt-6' : '' }}">
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-placeholder/20">
                            <span class="h-4 w-4 rounded-full" style="background-color: var(--color-{{ $pillar['color'] }})"></span>
                        </span>
                        <p class="mt-3 text-sm font-semibold text-ink">{{ $pillar['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <span class="inline-flex items-center rounded-full bg-primary/10 px-4 py-1.5 text-xs font-semibold tracking-wide text-primary uppercase">
                {{ __('home.trust.eyebrow') }}
            </span>

            <h2 class="mt-4 font-heading text-2xl font-bold text-ink lg:text-3xl">
                {{ __('home.trust.heading_prefix') }} <span class="text-primary">Findex</span>
            </h2>
            <p class="mt-4 max-w-xl text-sm leading-relaxed text-muted">{{ __('home.trust.body') }}</p>

            {{-- grid-cols-2 below sm - 3 columns left each card too narrow, causing a long translated value (e.g. Armenian's "Իրական ժամանակում") to overflow. --}}
            <div class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3">
                @foreach ($stats as $stat)
                    <div class="rounded-2xl border border-primary/20 bg-white px-2 py-5 text-center shadow-sm sm:px-4">
                        {{-- break-words - a single long word can still be wider than the card even with wrapping allowed (same fix as the hero heading in hero-carousel.blade.php). --}}
                        <p class="font-heading text-lg font-bold break-words text-primary sm:text-xl">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs font-medium break-words text-muted">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>

            <a
                href="{{ route('about') }}"
                class="group mt-8 inline-flex items-center gap-2 bg-primary px-8 py-3 text-sm font-medium text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-primary-dark hover:shadow-md"
            >
                {{ __('common.learn_more') }}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current transition-transform duration-300 group-hover:translate-x-1">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </a>
        </div>
    </div>
</section>
