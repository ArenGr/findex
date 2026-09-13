@props(['row', 'index', 'position', 'payment'])

{{-- One bank's mortgage offer. --}}
<div
    x-show="view.positions[{{ $index }}] !== undefined"
    @if ($position === null) x-cloak @endif
    :style="{ order: view.positions[{{ $index }}] }"
    style="order: {{ $position ?? 0 }}"
    class="flex items-center gap-4 border-b border-placeholder px-6 py-5"
>
    <span
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $position === 0 ? 'bg-accent-yellow text-ink' : 'bg-placeholder/60 text-muted' }}"
        :class="{
            'bg-accent-yellow text-ink': view.positions[{{ $index }}] === 0,
            'bg-placeholder/60 text-muted': view.positions[{{ $index }}] !== 0,
        }"
        x-text="rank({{ $index }})"
    >{{ $position === null ? '' : $position + 1 }}</span>

    @if ($row['logo'])
        <img src="{{ $row['logo'] }}" alt="{{ $row['name'] }}" width="40" height="40" loading="lazy" decoding="async" class="h-10 w-10 shrink-0 rounded-full object-contain">
    @else
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">{{ $row['initial'] }}</div>
    @endif

    <div class="min-w-0 flex-1">
        <a href="{{ $row['url'] }}" class="block truncate text-sm font-medium text-ink hover:text-primary">{{ $row['name'] }}</a>
        @if ($row['reviews_count'] > 0)
            <div class="mt-0.5 flex items-center gap-1">
                <svg viewBox="0 0 20 20" class="h-3 w-3 fill-accent-yellow"><path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" /></svg>
                <span class="text-xs text-subtle">{{ number_format($row['rating'], 1, '.', '') }} ({{ $row['reviews_count'] }})</span>
            </div>
        @endif
        <div class="mt-1 flex flex-wrap gap-1">
            @foreach (array_diff($row['badges'], ['rate_only']) as $badge)
                <span class="rounded-full bg-placeholder/50 px-2 py-0.5 text-[11px] text-ink">{{ __('offers.mortgage_ranking.badge_'.$badge) }}</span>
            @endforeach
        </div>
    </div>

    <div class="hidden w-24 shrink-0 text-right sm:block">
        <p class="text-sm font-semibold text-ink">{{ $row['eff_rate'] }}%</p>
        <p class="text-[11px] text-subtle">{{ __('offers.mortgage_ranking.basis_'.$row['basis']) }}</p>
    </div>

    <div class="hidden w-24 shrink-0 text-right md:block">
        <p class="text-sm text-ink">{{ $row['min_down_payment_percent'] }}%+</p>
    </div>

    <div class="w-32 shrink-0 text-right">
        {{-- The one figure the calculator actually recomputes. --}}
        <p class="font-heading text-lg font-bold text-primary" x-text="paymentLabel({{ $index }})">{{ $payment === null ? '' : number_format(round($payment), 0, '.', ',') }}</p>
        <p class="text-xs text-subtle">/ {{ __('offers.per_month') }}</p>
    </div>

    <div class="hidden w-24 shrink-0 text-right sm:block">
        @if ($row['source_url'])
            <a href="{{ $row['source_url'] }}" target="_blank" rel="noopener" class="text-xs font-medium text-primary hover:underline">{{ __('offers.mortgage_table.view_details') }}</a>
        @endif
    </div>
</div>
