@props(['row', 'index'])

{{-- One bank's rate for the homepage table. --}}
<div :style="{ order: positions[{{ $index }}] }" class="flex items-center gap-4 border-b border-border px-6 py-5 transition-colors hover:bg-surface-alt">
    <span
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $index === 0 ? 'bg-accent-yellow text-ink' : 'bg-placeholder/60 text-muted' }}"
        :class="{
            'bg-accent-yellow text-ink': positions[{{ $index }}] === 0,
            'bg-placeholder/60 text-muted': positions[{{ $index }}] !== 0,
        }"
        x-text="positions[{{ $index }}] + 1"
    >{{ $index + 1 }}</span>

    <a @if ($row['url']) href="{{ $row['url'] }}" @endif class="block shrink-0" aria-label="{{ $row['name'] }}">
        <x-rates.org-mark :logo="$row['logo']" :name="$row['name']" />
    </a>

    <div class="min-w-0 flex-1 overflow-hidden">
        <div class="hidden sm:block">
            <a @if ($row['url']) href="{{ $row['url'] }}" @endif class="block truncate text-sm font-semibold text-ink hover:text-primary">{{ $row['name'] }}</a>
            @if ($row['reviews_count'] > 0)
                <div class="mt-0.5 flex min-w-0 items-center gap-1">
                    <svg viewBox="0 0 20 20" class="h-3 w-3 shrink-0 fill-accent-yellow"><use href="#findex-star" /></svg>
                    <span class="truncate text-xs text-subtle">{{ number_format($row['rating'], 1, '.', '') }} ({{ $row['reviews_count'] }})</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Buy green, sell red - the same reading as every other rates list. --}}
    <div class="w-20 text-right"><p class="text-lg font-bold text-primary tabular-nums sm:text-xl">{{ number_format($row['buy_rate'], 2, '.', '') }}</p></div>
    <div class="w-20 text-right"><p class="text-lg font-bold text-accent-red tabular-nums sm:text-xl">{{ number_format($row['sell_rate'], 2, '.', '') }}</p></div>

    <div class="hidden w-24 shrink-0 text-right sm:block">
        <p class="text-sm font-medium text-ink">{{ number_format($row['spread'], 2, '.', '') }}</p>
        <p class="text-xs text-subtle">{{ $row['updated'] ?? '—' }}</p>
    </div>
</div>
