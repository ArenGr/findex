@props(['steps', 'current' => 1])

{{-- The shared step indicator for the multi-step request flows, sitting inside the hero on its tint. --}}
<ol class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-0">
    @foreach ($steps as $i => $step)
        @php $n = $i + 1; $done = $n < $current; $isCurrent = $n === $current; @endphp
        <li class="flex items-center gap-3 {{ $n < count($steps) ? 'sm:flex-1' : '' }}">
            <span @class([
                'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[13px] font-semibold',
                'bg-primary text-white' => $done || $isCurrent,
                'border border-placeholder bg-white text-muted' => ! $done && ! $isCurrent,
            ])>
                @if ($done)
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-[18px] w-[18px]" aria-hidden="true"><path d="m5 13 4 4L19 7" /></svg>
                @else
                    {{ $n }}
                @endif
            </span>

            <span class="min-w-0">
                <span @class(['block text-[13px] leading-4 font-semibold', 'text-ink' => $done || $isCurrent, 'text-muted' => ! $done && ! $isCurrent])>{{ $step['title'] }}</span>
                <span class="mt-0.5 block text-[11px] leading-4 text-muted">{{ $step['body'] }}</span>
            </span>

            @if ($n < count($steps))
                <span @class([
                    'mx-3 hidden h-px flex-1 sm:block',
                    'bg-primary' => $done,
                    'bg-placeholder' => ! $done,
                ])></span>
            @endif
        </li>
    @endforeach
</ol>
