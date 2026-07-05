@php
    $slides = [
        [
            'badge' => 'bg-slide-green/20 text-ink',
            'button' => 'bg-slide-green text-white hover:bg-primary-dark',
            'dot' => 'bg-slide-green',
            'blob' => 'bg-slide-green/20',
            'photo' => 'slide-1.jpg',
            'alt' => __('hero.alt.slide_1'),
        ],
        [
            'badge' => 'bg-slide-blue text-ink',
            'button' => 'bg-slide-blue text-ink hover:opacity-90',
            'dot' => 'bg-slide-blue',
            'blob' => 'bg-slide-blue/40',
            'photo' => 'slide-2.jpg',
            'alt' => __('hero.alt.slide_2'),
        ],
        [
            'badge' => 'bg-slide-yellow text-ink',
            'button' => 'bg-slide-yellow text-ink hover:opacity-90',
            'dot' => 'bg-slide-yellow',
            'blob' => 'bg-slide-yellow/40',
            'photo' => 'slide-3.jpg',
            'alt' => __('hero.alt.slide_3'),
        ],
        [
            'badge' => 'bg-slide-pink text-ink',
            'button' => 'bg-slide-pink text-ink hover:opacity-90',
            'dot' => 'bg-slide-pink',
            'blob' => 'bg-slide-pink/40',
            'photo' => 'slide-4.jpg',
            'alt' => __('hero.alt.slide_4'),
        ],
        [
            'badge' => 'bg-slide-purple text-ink',
            'button' => 'bg-slide-purple text-ink hover:opacity-90',
            'dot' => 'bg-slide-purple',
            'blob' => 'bg-slide-purple/40',
            'photo' => 'slide-5.jpg',
            'alt' => __('hero.alt.slide_5'),
        ],
    ];
@endphp

<section
    x-data="{ active: 0, total: {{ count($slides) }} }"
    x-init="setInterval(() => active = (active + 1) % total, 6000)"
    class="mx-auto max-w-7xl px-6 py-16 lg:px-10"
>
    <div class="relative">
        @foreach ($slides as $i => $slide)
            <div
                x-show="active === {{ $i }}"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                @if ($i > 0) x-cloak @endif
                class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2"
            >
                {{-- Text column --}}
                <div>
                    <span class="relative inline-flex rounded-full px-4 py-2 text-sm font-medium {{ $slide['badge'] }}">
                        {{ __('hero.badge') }}
                        <span class="absolute -bottom-1.5 left-6 h-3 w-3 rotate-45 {{ $slide['badge'] }}"></span>
                    </span>

                    <h1 class="mt-6 font-heading text-4xl leading-tight font-bold text-ink lg:text-5xl">
                        {!! __('hero.heading') !!}
                    </h1>

                    <p class="mt-4 max-w-md text-sm leading-relaxed text-muted">
                        {{ __('hero.paragraph') }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a href="#" class="px-6 py-3 text-sm font-medium transition {{ $slide['button'] }}">
                            {{ __('common.learn_more') }}
                        </a>
                        <a href="#" class="border border-ink px-6 py-3 text-sm font-medium text-ink transition hover:bg-ink hover:text-white">
                            {{ __('common.compare_banks') }}
                        </a>
                    </div>
                </div>

                {{-- Photo column --}}
                <div class="relative">
                    <div class="absolute inset-x-6 -bottom-6 -right-6 top-6 rounded-3xl {{ $slide['blob'] }}"></div>

                    <div class="overflow-hidden rounded-3xl">
                        <img
                            src="{{ asset('images/hero/' . $slide['photo']) }}"
                            alt="{{ $slide['alt'] }}"
                            width="874"
                            height="428"
                            loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                            class="h-auto w-full object-cover"
                        >
                    </div>

                    <div class="absolute top-6 -left-4 flex flex-col items-start gap-2">
                        <span class="relative rounded-2xl bg-white px-4 py-2 text-xs font-medium whitespace-nowrap text-ink shadow-md">
                            {{ __('hero.bubble_question') }}
                        </span>
                        <span class="relative ml-4 w-[240px] rounded-2xl bg-white px-4 py-3 text-xs leading-relaxed text-ink shadow-md">
                            {{ __('hero.bubble_answer') }}
                            <span class="absolute -bottom-1.5 left-6 h-3 w-3 rotate-45 bg-white"></span>
                        </span>
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
                    :class="active === {{ $i }} ? '{{ $slide['dot'] }} w-6' : 'bg-border-muted w-2'"
                    class="h-2 rounded-full transition-all"
                    aria-label="{{ __('hero.go_to_slide', ['n' => $i + 1]) }}"
                ></button>
            @endforeach
        </div>
    </div>
</section>
