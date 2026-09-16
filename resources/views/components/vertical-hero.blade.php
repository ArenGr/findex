@props([
    'icon',
    'eyebrow',
    'title',
    'subtitle' => null,
    'signals' => [],
])

{{--
    The top of a Findex vertical - rates, insurance, travel and whatever comes
    next. All of them do the same job, so all of them open the same way:

        [icon] CATEGORY
        one headline
        one short explanation
        ┌ the panel that collects the answer ┐
        └────────────────────────────────────┘
        [icon] signal   [icon] signal   [icon] signal

    Only the words and the fields inside the panel change between them. A page
    that wants a different hero should get a better reason than "it is a
    different page".

    `signals` is a list of ['icon' => lucide name, 'title' => ..., 'sub' => ...].
    Prefer a real number over a claim - "12 insurers compared" beats "trusted".
--}}
<section {{ $attributes->merge(['class' => 'border-b border-border bg-surface-alt']) }}>
    <div class="site-container pt-10 pb-10 lg:pt-14 lg:pb-12">
        <div class="max-w-3xl">
            <span class="inline-flex items-center gap-2 rounded-full border border-border bg-surface px-3 py-1.5 text-xs font-bold tracking-wider text-primary uppercase">
                <x-lucide :name="$icon" :size="14" />
                {{ $eyebrow }}
            </span>

            <h1 class="mt-4 font-heading text-3xl leading-tight font-bold break-words text-ink sm:text-4xl lg:text-5xl">
                {{ $title }}
            </h1>

            @if ($subtitle)
                <p class="mt-4 text-base leading-relaxed break-words text-muted lg:text-lg lg:leading-8">{{ $subtitle }}</p>
            @endif
        </div>

        {{-- The collect step. One white panel, same shape on every vertical. --}}
        @isset($panel)
            <div class="mt-8 rounded-3xl border border-border bg-surface p-5 shadow-[0_1px_2px_rgb(29_36_28/0.04)] sm:p-6 lg:mt-10">
                {{ $panel }}
            </div>
        @endisset

        @if ($signals !== [])
            <ul class="mt-7 flex flex-wrap items-center gap-x-10 gap-y-4">
                @foreach ($signals as $signal)
                    <li class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface text-primary ring-1 ring-border">
                            <x-lucide :name="$signal['icon']" :size="18" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-ink">{{ $signal['title'] }}</span>
                            @isset($signal['sub'])
                                <span class="block text-xs text-muted">{{ $signal['sub'] }}</span>
                            @endisset
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
