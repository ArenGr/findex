@php
    // One slide per top-level nav category (Finance/Insurance/Travel), each
    // linking straight to that category's real page.
    $slides = [
        [
            'badge' => 'bg-slide-green-pastel text-ink',
            'button' => 'bg-slide-green text-white hover:bg-primary-dark',
            'dot' => 'bg-slide-green',
            'blob' => 'bg-slide-green/20',
            'photo' => 'slide-1.jpg',
            // route('banks.index') would land on the category hub rather
            // than a real comparison - deep-linking straight to "mortgages"
            // sends visitors to the one category with a real, working
            // comparison table behind it.
            'href' => route('banks.show', 'mortgages'),
        ],
        [
            'badge' => 'bg-slide-blue text-ink',
            'button' => 'bg-slide-blue text-ink hover:opacity-90',
            'dot' => 'bg-slide-blue',
            'blob' => 'bg-slide-blue/40',
            'photo' => 'slide-2.jpg',
            'href' => route('rates.index'),
        ],
        [
            'badge' => 'bg-slide-pink text-ink',
            'button' => 'bg-slide-pink text-ink hover:opacity-90',
            'dot' => 'bg-slide-pink',
            'blob' => 'bg-slide-pink/40',
            'photo' => 'slide-4.jpg',
            'href' => route('insurance.auto.request'),
        ],
        [
            'badge' => 'bg-slide-purple text-ink',
            'button' => 'bg-slide-purple text-ink hover:opacity-90',
            'dot' => 'bg-slide-purple',
            'blob' => 'bg-slide-purple/40',
            'photo' => 'slide-5.jpg',
            'href' => route('tourism.request'),
        ],
    ];

    /*
     * Real pixel dimensions, read from the files rather than written down.
     *
     * They were hardcoded as 874x428 - the size of slide-3.jpg, which this
     * carousel does not even use. The four it does use are 775x431, so every
     * slide reserved a box 23px too short at phone widths and snapped taller
     * the moment the image decoded, taking the rest of the page with it.
     * Reading them means replacing an image cannot reintroduce that.
     *
     * getimagesize() only reads the header, and these are four small local
     * files - cheap next to what the rest of the homepage already does.
     */
    foreach ($slides as $i => $slide) {
        [$width, $height] = getimagesize(public_path('images/hero/'.$slide['photo']));
        $slides[$i]['width'] = $width;
        $slides[$i]['height'] = $height;
    }
@endphp

<section
    x-data="{ active: 0, total: {{ count($slides) }} }"
    x-init="setInterval(() => active = (active + 1) % total, 6000)"
    class="site-container py-16"
>
    <div class="lg:flex lg:items-start lg:gap-10">
    <div class="min-w-0 flex-1">
    <div class="relative grid">
        @foreach ($slides as $i => $slide)
            @php($n = $i + 1)
            {{--
                Every slide is laid out from the first paint, at opacity 0 for
                all but the active one. It used to be x-cloak'd instead, which
                meant the browser sized this grid cell to slide 1 alone until
                Alpine booted and then re-sized it to the tallest slide - a
                ~190px jump that shoved the entire page down. The state below
                is the state Alpine would compute anyway, so nothing moves
                when it takes over.

                :class uses the object form, not a ternary. A ternary only
                removes the classes Alpine itself added, so the server-rendered
                opacity-100 on slide 1 would survive forever and the slide
                would never fade out. The object form removes whatever it is
                told is false, wherever it came from.
            --}}
            <div
                @if ($i > 0) inert @endif
                :inert="active !== {{ $i }}"
                :class="{ 'opacity-100': active === {{ $i }}, 'opacity-0': active !== {{ $i }} }"
                class="col-start-1 row-start-1 grid grid-cols-1 items-center gap-12 opacity-{{ $i === 0 ? '100' : '0' }} transition-opacity duration-700 ease-in-out lg:grid-cols-2"
            >
                {{-- Text column --}}
                <div>
                    <span class="relative inline-flex rounded-full px-4 py-2 text-sm font-medium shadow-sm {{ $slide['badge'] }}">
                        {{ __("hero.slides.$n.badge") }}
                        <span class="absolute -bottom-1.5 left-6 h-3 w-3 rotate-45 {{ $slide['badge'] }}"></span>
                    </span>

                    {{--
                        text-3xl/lg:text-4xl (not larger) so Armenian/Russian
                        headings, longer than their English equivalents,
                        don't wrap to 3-4 lines and push the CTAs off-screen.
                        break-words handles single long words (e.g.
                        Armenian's ~20-char "Ավտոապահովագրության") that are
                        wider than the column even with normal wrapping -
                        the lang/*/hero.php strings used to force a break
                        with a hardcoded <br>, which doesn't adapt per
                        language/viewport, so it's gone in favor of this.
                    --}}
                    <h1 class="mt-6 font-heading text-3xl leading-tight font-bold break-words text-ink lg:text-4xl">
                        {!! __("hero.slides.$n.heading") !!}
                    </h1>

                    <p class="mt-4 max-w-md text-sm leading-relaxed text-muted">
                        {{ __("hero.slides.$n.paragraph") }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a href="{{ $slide['href'] }}" class="px-6 py-3 text-sm font-medium shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md {{ $slide['button'] }}">
                            {{ __("hero.slides.$n.cta") }}
                        </a>
                        <a href="{{ route('organizations.compare') }}" class="btn btn-secondary">
                            {{ __('common.compare_banks') }}
                        </a>
                    </div>
                </div>

                {{-- Photo column --}}
                <div class="relative">
                    {{-- The overhang has to stay inside the column's padding or
                         it gives the whole document a horizontal scrollbar: the
                         container pads 16px on a phone, so a 24px pull escapes
                         the viewport by 8px. --}}
                    <div class="absolute inset-x-6 top-6 -right-3 -bottom-6 rounded-3xl sm:-right-6 {{ $slide['blob'] }}"></div>

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
                {{-- Same story as the slides: the width lived only in the
                     Alpine binding, so every dot painted at zero width and
                     then popped out to 2/6px once Alpine ran. --}}
                <button
                    type="button"
                    @click="active = {{ $i }}"
                    :class="{ '{{ $slide['dot'] }} w-6': active === {{ $i }}, 'bg-border-muted w-2': active !== {{ $i }} }"
                    class="h-2 rounded-full transition-all {{ $i === 0 ? $slide['dot'].' w-6' : 'bg-border-muted w-2' }}"
                    aria-label="{{ __('hero.go_to_slide', ['n' => $i + 1]) }}"
                ></button>
            @endforeach
        </div>
    </div>
    </div>

    <x-ad-slot placement="home_hero" />
    </div>
</section>
