@props(['paginator'])

@if ($paginator->hasPages())
    @php
        // A short window around the current page. The whole market is at most
        // a handful of pages, so this rarely elides anything - but a currency
        // with every organization quoting it should not print thirty numbers.
        $last = $paginator->lastPage();
        $current = $paginator->currentPage();
        $pages = collect(range(1, $last))
            ->filter(fn (int $page) => $page === 1 || $page === $last || abs($page - $current) <= 1)
            ->values();
    @endphp

    <nav class="mt-6 flex items-center justify-center gap-1.5" aria-label="{{ __('rates.pagination_label') }}">
        {{-- Present but inert at the ends rather than removed: a control that
        disappears makes the row shift under the pointer that was aiming at it. --}}
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-placeholder text-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 rtl:rotate-180" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('rates.pagination_previous') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-placeholder text-muted transition hover:border-border-muted hover:text-ink">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 rtl:rotate-180" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
            </a>
        @endif

        @foreach ($pages as $index => $page)
            {{-- A gap in the run means pages were skipped, and saying so is
            more honest than numbers that jump without explanation. --}}
            @if ($index > 0 && $page - $pages[$index - 1] > 1)
                <span class="px-1 text-sm text-placeholder" aria-hidden="true">&hellip;</span>
            @endif

            @if ($page === $current)
                <span aria-current="page" class="inline-flex h-10 min-w-10 items-center justify-center rounded-lg border border-primary/50 bg-primary/10 px-3 text-sm font-semibold text-ink tabular-nums">
                    {{ $page }}
                </span>
            @else
                <a href="{{ $paginator->url($page) }}" class="inline-flex h-10 min-w-10 items-center justify-center rounded-lg border border-placeholder px-3 text-sm font-medium text-muted tabular-nums transition hover:border-border-muted hover:text-ink">
                    {{ $page }}
                </a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('rates.pagination_next') }}" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-placeholder text-muted transition hover:border-border-muted hover:text-ink">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 rtl:rotate-180" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </a>
        @else
            <span aria-disabled="true" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-placeholder text-placeholder">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 rtl:rotate-180" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
            </span>
        @endif
    </nav>
@endif
