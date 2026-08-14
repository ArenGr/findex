<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Findex — {{ $currency->code }}</title>
    <meta name="robots" content="noindex">

    {{--
        No app layout and no shared stylesheet: this renders inside somebody
        else's page, so it carries only what it needs. The brand colours are
        repeated here as literals rather than pulled from the Tailwind build,
        because shipping the site's whole stylesheet into an iframe to colour
        four numbers is not a trade worth making.
    --}}
    <style>
        :root {
            --ink: #161515; --muted: #676767; --line: #d9d9d9;
            --buy: #607e34; --sell: #ba1a1a; --bg: #ffffff;
        }
        .dark {
            --ink: #efeee3; --muted: #a3a698; --line: #2f3427;
            --buy: #9dbb6a; --sell: #ef7b7b; --bg: #14160f;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 12px; background: var(--bg); color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 14px; line-height: 1.4;
        }
        .card { border: 1px solid var(--line); border-radius: 12px; padding: 14px 16px; }
        .label { font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
        .row { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        /* Fluid, because the host decides the box and 200px is a box people
           genuinely use - two six-character rates at a fixed 26px do not fit
           one, and a widget that overflows its iframe looks broken. */
        .rate { font-size: clamp(17px, 8.5vw, 26px); font-weight: 700; font-variant-numeric: tabular-nums; }
        .buy { color: var(--buy); } .sell { color: var(--sell); }
        .who { font-size: 12px; color: var(--muted); overflow-wrap: anywhere; }
        .foot { margin-top: 10px; font-size: 11px; color: var(--muted); }
        .foot a { color: inherit; }
        input, select {
            width: 100%; padding: 8px 10px; border: 1px solid var(--line); border-radius: 8px;
            background: var(--bg); color: var(--ink); font: inherit;
        }
        svg { display: block; width: 100%; height: 64px; }
    </style>
</head>
<body class="{{ $dark ? 'dark' : '' }}">
    @php
        $buy = $best['highest_buy'] ?? null;
        $sell = $best['lowest_sell'] ?? null;
        $site = route('rates.index', ['locale' => config('localization.default'), 'currency' => $currency->code]);
    @endphp

    <div class="card">
        @if ($type === 'converter')
            <div class="label">{{ $currency->code }} &rarr; AMD</div>
            {{-- Stacked, not side by side: a six-figure dram total does not
            fit half of a 240px embed, and the answer being clipped is worse
            than the widget being one row taller. --}}
            <div style="margin-top:8px; display:grid; gap:8px;">
                <input id="amount" type="number" inputmode="decimal" min="0" value="100" aria-label="{{ $currency->code }}">
                <input id="result" type="text" readonly aria-label="AMD" style="font-weight:600;">
            </div>
            <div class="who" style="margin-top:8px;">
                {{ $buy ? number_format($buy['rate'], 2).' AMD / 1 '.$currency->code : '—' }}
            </div>

            {{-- The rate is printed into the page rather than fetched: the
            widget must render before any network round trip on a host page we
            do not control. --}}
            <script>
                (function () {
                    var rate = {{ $buy ? $buy['rate'] : 'null' }};
                    var amount = document.getElementById('amount');
                    var result = document.getElementById('result');

                    function convert() {
                        if (rate === null) { result.value = '—'; return; }
                        var value = Number(amount.value);
                        result.value = isFinite(value)
                            ? (value * rate).toLocaleString('en-US', { maximumFractionDigits: 2 }) + ' AMD'
                            : '—';
                    }

                    amount.addEventListener('input', convert);
                    convert();
                })();
            </script>

        @elseif ($type === 'chart' && $series !== [])
            <div class="label">{{ $currency->code }} &middot; {{ count($series) }}d</div>
            @php
                $values = array_column($series, 'best_buy');
                $min = min($values); $max = max($values);
                $span = ($max - $min) ?: 1;
                $points = implode(' ', array_map(
                    fn ($value, $i) => round($i / max(count($values) - 1, 1) * 300, 1).','.round(60 - (($value - $min) / $span) * 56, 1),
                    $values,
                    array_keys($values),
                ));
            @endphp
            <svg viewBox="0 0 300 64" preserveAspectRatio="none" role="img" aria-label="{{ $currency->code }}">
                <polyline points="{{ $points }}" fill="none" stroke="var(--buy)" stroke-width="2" vector-effect="non-scaling-stroke" />
            </svg>
            <div class="row" style="margin-top:6px;">
                <span class="who">{{ number_format($min, 2) }}</span>
                <span class="rate buy" style="font-size:18px;">{{ number_format(end($values), 2) }}</span>
                <span class="who">{{ number_format($max, 2) }}</span>
            </div>

        @else
            {{-- 'rate' and 'best' both answer "what can I get right now"; best
            names the organization, rate just gives the pair. --}}
            <div class="label">{{ $currency->code }} / AMD</div>
            <div class="row" style="margin-top:6px;">
                <div>
                    <div class="label">{{ __('rates.buy_column') }}</div>
                    <div class="rate buy">{{ $buy ? number_format($buy['rate'], 2) : '—' }}</div>
                </div>
                <div style="text-align:right;">
                    <div class="label">{{ __('rates.sell_column') }}</div>
                    <div class="rate sell">{{ $sell ? number_format($sell['rate'], 2) : '—' }}</div>
                </div>
            </div>

            @if ($type === 'best' && $buy)
                <div class="who" style="margin-top:8px;">{{ $buy['organization'] }}</div>
            @endif
        @endif

        {{-- The reason we give these away. --}}
        <div class="foot">
            <a href="{{ $site }}" target="_blank" rel="noopener">Powered by Findex</a>
        </div>
    </div>
</body>
</html>
