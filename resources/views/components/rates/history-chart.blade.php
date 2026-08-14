@props(['series', 'lines', 'height' => 220])

@php
    // Plain inline SVG rather than a charting library: two polylines over a
    // handful of points does not justify shipping one, and §53 asks for the
    // page to stay light.
    $values = collect($series)
        ->flatMap(fn (array $point) => collect($lines)->keys()->map(fn ($key) => $point[$key] ?? null))
        ->filter(fn ($value) => $value !== null);

    $min = $values->min();
    $max = $values->max();

    // A flat line would divide by zero and render at the very top; padding the
    // range puts it sensibly in the middle instead.
    $span = ($max - $min) ?: max(abs($max) * 0.01, 0.01);
    $min -= $span * 0.15;
    $max += $span * 0.15;

    $width = 720;
    $count = max(count($series) - 1, 1);

    $x = fn (int $index) => round($index / $count * $width, 2);
    $y = fn (float $value) => round($height - (($value - $min) / ($max - $min)) * $height, 2);
@endphp

<figure {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <div class="overflow-x-auto">
        <svg
            viewBox="0 0 {{ $width }} {{ $height }}"
            preserveAspectRatio="none"
            class="h-56 w-full min-w-[20rem]"
            role="img"
            aria-label="{{ $attributes->get('aria-label') }}"
        >
            {{-- Four faint guides, so a reader can judge a slope rather than
            only see one. --}}
            @foreach ([0, 0.25, 0.5, 0.75, 1] as $step)
                <line x1="0" x2="{{ $width }}" y1="{{ $height * $step }}" y2="{{ $height * $step }}"
                      stroke="currentColor" stroke-width="1" class="text-placeholder" />
            @endforeach

            @foreach ($lines as $key => $line)
                @php
                    $points = collect($series)
                        ->map(fn (array $point, int $index) => ($point[$key] ?? null) === null ? null : $x($index).','.$y((float) $point[$key]))
                        ->filter()
                        ->implode(' ');
                @endphp

                @if ($points !== '')
                    <polyline points="{{ $points }}" fill="none" stroke="{{ $line['color'] }}" stroke-width="2.5"
                              stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                    {{-- The last point marked: "where it is now" is the thing
                    most readers are actually looking for. --}}
                    @php $last = collect($series)->last(); @endphp
                    @if (($last[$key] ?? null) !== null)
                        <circle cx="{{ $x(count($series) - 1) }}" cy="{{ $y((float) $last[$key]) }}" r="4" fill="{{ $line['color'] }}" />
                    @endif
                @endif
            @endforeach
        </svg>
    </div>

    <figcaption class="mt-3 flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-xs text-muted">
        <span class="flex flex-wrap items-center gap-x-4 gap-y-1">
            @foreach ($lines as $line)
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-0.5 w-4 shrink-0 rounded" style="background: {{ $line['color'] }}"></span>
                    <span class="min-w-0 break-words">{{ $line['label'] }}</span>
                </span>
            @endforeach
        </span>

        <span class="tabular-nums">
            {{ \Illuminate\Support\Carbon::parse(collect($series)->first()['date'])->translatedFormat('d M') }}
            &ndash;
            {{ \Illuminate\Support\Carbon::parse(collect($series)->last()['date'])->translatedFormat('d M') }}
        </span>
    </figcaption>
</figure>
