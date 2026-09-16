@props([
    'label',
    'value',
    'note',
    'hint',
    'variant' => 'neutral',   // buy | sell | neutral
    'badge' => null,
])

@php
    // One card, three numbers. The colour lives in the figure, not behind it -
    // a tinted card per variant was the loudest thing on the page and read as
    // three different components.
    $tone = ['buy' => 'text-primary', 'sell' => 'text-accent-red', 'neutral' => 'text-ink'][$variant] ?? 'text-ink';
@endphp

<article class="flex min-w-0 flex-col rounded-2xl border border-border bg-surface p-5 sm:p-6">
    <span class="flex items-center gap-1.5">
        <span class="text-[11px] font-semibold tracking-wider break-words text-muted uppercase sm:text-xs">{{ $label }}</span>
        <x-info-popover :label="$label">{{ $hint }}</x-info-popover>
    </span>

    <p class="mt-3 flex items-end gap-1.5 whitespace-nowrap">
        <span class="text-4xl font-semibold tracking-tight tabular-nums sm:text-5xl {{ $tone }}">{{ number_format((float) $value, 2) }}</span>
        <span class="pb-1.5 text-sm text-muted">{{ __('exchange_quotes.request.amd') }}</span>
    </p>

    <div class="mt-4 flex items-center justify-between gap-2">
        <span class="min-w-0 truncate text-sm text-muted" title="{{ $note }}">{{ $note }}</span>
        @if ($badge)
            <span class="shrink-0 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">{{ $badge }}</span>
        @endif
    </div>
</article>
