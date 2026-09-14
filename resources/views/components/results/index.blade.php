@props([
    'count' => null,
    'summary' => null,
])

{{--
    The compare step, shared by every results page.

        ← search summary
        N results                        Sort by [ ... ]
        ┌ filters ┐  ┌ results ─────────┐
        │         │  │                  │
        └─────────┘  └──────────────────┘

    Rates, insurance quotes, travel offers and the organization directory are
    the same screen with different rows in it, so they are the same component.
    Learn one and you know them all.

    Filters are a rail from lg and a disclosure above the results below it -
    the slot is rendered once either way, so a page writes its filters once.
--}}
<section {{ $attributes->merge(['class' => 'site-container py-12 lg:py-16']) }}>
    @if ($summary)
        <p class="text-sm break-words text-muted">{{ $summary }}</p>
    @endif

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-heading text-xl font-semibold text-ink lg:text-2xl">
            {{ $count !== null ? trans_choice('compare_ui.count', $count, ['count' => number_format($count)]) : $heading ?? '' }}
        </h2>

        @isset($sort)
            <div class="flex items-center gap-2 text-sm text-muted">
                <span class="shrink-0">{{ __('compare_ui.sort_by') }}</span>
                {{ $sort }}
            </div>
        @endisset
    </div>

    <div class="mt-6 lg:grid lg:grid-cols-[260px_minmax(0,1fr)] lg:items-start lg:gap-8">
        @isset($filters)
            {{-- One control per filter, each stating its own answer. --}}
            <aside
                x-data="{ open: false }"
                class="mb-6 lg:mb-0"
            >
                <button
                    type="button"
                    @click="open = !open"
                    :aria-expanded="open"
                    class="btn btn-secondary w-full lg:hidden"
                >
                    {{ __('compare_ui.filters') }}
                </button>

                <div
                    x-cloak
                    :class="open ? 'block' : 'hidden'"
                    class="mt-3 rounded-2xl border border-border bg-surface p-5 lg:mt-0 lg:!block"
                >
                    {{ $filters }}
                </div>
            </aside>
        @endisset

        <div class="min-w-0">
            {{ $slot }}

            @isset($pagination)
                <div class="mt-8">{{ $pagination }}</div>
            @endisset
        </div>
    </div>

    @isset($empty)
        {{ $empty }}
    @endisset
</section>
