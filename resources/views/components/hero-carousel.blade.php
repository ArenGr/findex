@php
    $feature = fn(string $key) => \App\Support\Features::enabled($key);

    // A slide is a photograph and a link. It does not get a palette of its
    // own - badge, button and dot are the same on every slide.
    $slides = array_values(array_filter([
        [
            'n' => 1,
            'photo' => 'slide-1.jpg',
            'href' => $feature('mortgages') ? route('banks.show', 'mortgages') : null,
        ],
        [
            'n' => 2,
            'photo' => 'slide-2.jpg',
            'href' => $feature('rates') ? route('rates.index') : null,
        ],
        [
            'n' => 3,
            'photo' => 'slide-4.jpg',
            'href' => $feature('insurance') ? route('insurance.auto.request') : null,
        ],
        [
            'n' => 4,
            'photo' => 'slide-5.jpg',
            'href' => $feature('travel') ? route('tourism.request') : null,
        ],
    ], fn(array $slide) => $slide['href'] !== null));

    // Real pixel dimensions, read from the files rather than written down.
    foreach ($slides as $i => $slide) {
        [$width, $height] = getimagesize(public_path('images/hero/'.$slide['photo']));
        $slides[$i]['width'] = $width;
        $slides[$i]['height'] = $height;
    }
@endphp

@if ($slides !== [])
<section
    x-data="{ active: 0, total: {{ count($slides) }} }"
    x-init="setInterval(() => active = (active + 1) % total, 6000)"
    class="site-container py-16"
>
    <div class="lg:flex lg:items-start lg:gap-10">
    <div class="min-w-0 flex-1">
    <div class="relative grid">
        @foreach ($slides as $i => $slide)
            @php($n = $slide['n'])
            {{-- Every slide is laid out from the first paint, at opacity 0 for all but the active one. --}}
            <div
                @if ($i > 0) inert @endif
                :inert="active !== {{ $i }}"
                :class="{ 'opacity-100': active === {{ $i }}, 'opacity-0': active !== {{ $i }} }"
                class="col-start-1 row-start-1 grid grid-cols-1 items-center gap-12 opacity-{{ $i === 0 ? '100' : '0' }} transition-opacity duration-700 ease-in-out lg:grid-cols-2"
            >
                {{-- Text column --}}
                <div>
                    <span class="relative inline-flex rounded-full px-4 py-2 text-sm font-medium shadow-sm bg-surface-alt text-ink">
                        {{ __("hero.slides.$n.badge") }}
                        <span class="absolute -bottom-1.5 left-6 h-3 w-3 rotate-45 bg-surface-alt text-ink"></span>
                    </span>

                    <h1 class="mt-6 font-heading text-3xl leading-tight font-bold break-words text-ink lg:text-4xl">
                        {!! __("hero.slides.$n.heading") !!}
                    </h1>

                    <p class="mt-4 max-w-md text-sm leading-relaxed text-muted">
                        {{ __("hero.slides.$n.paragraph") }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a href="{{ $slide['href'] }}" class="px-6 py-3 text-sm font-medium shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md bg-primary text-white hover:bg-primary-dark">
                            {{ __("hero.slides.$n.cta") }}
                        </a>
                        @feature('compare')
                            <a href="{{ route('organizations.compare') }}" class="btn btn-secondary">
                                {{ __('common.compare_banks') }}
                            </a>
                        @endfeature
                    </div>
                </div>

                {{-- Photo column --}}
                <div class="relative">

                    <div class="overflow-hidden rounded-3xl shadow-xl">
                        <img
                            src="{{ asset('images/hero/' . $slide['photo']) }}"
                            alt="{{ __("hero.slides.$n.alt") }}"
                            width="{{ $slide['width'] }}"
                            height="{{ $slide['height'] }}"
                            loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                            class="h-auto w-full object-cover"
                        >
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Dots --}}
        <div class="mt-8 flex items-center gap-2">
            @foreach ($slides as $i => $slide)
                <button
                    type="button"
                    @click="active = {{ $i }}"
                    :class="{ 'bg-primary w-6': active === {{ $i }}, 'bg-border-muted w-2': active !== {{ $i }} }"
                    class="h-2 rounded-full transition-all {{ $i === 0 ? 'bg-primary w-6' : 'bg-border-muted w-2' }}"
                    aria-label="{{ __('hero.go_to_slide', ['n' => $i + 1]) }}"
                ></button>
            @endforeach
        </div>
    </div>
    </div>

    <x-ad-slot placement="home_hero" />
    </div>
</section>
@endif
