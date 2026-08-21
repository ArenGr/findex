@props(['branch'])

@php
    $week = $branch->weeklyHours();
@endphp

{{--
    The full week beside the "open now" badge, which only ever answers for
    today. Someone deciding whether to go tomorrow, or on Saturday, needs the
    whole schedule - and it is the thing the banks publish that this app was
    throwing away.

    Runs are collapsed by Branch::weeklyHours() ("Mon - Fri", then "Sat"), so
    this renders two or three rows rather than seven.
--}}
@if ($week !== [])
    <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs">
        @foreach ($week as $run)
            <dt class="text-muted">
                {{ __('rates.'.$run['from']) }}@unless ($run['from'] === $run['to']) &ndash; {{ __('rates.'.$run['to']) }}@endunless
            </dt>
            <dd @class(['tabular-nums', 'text-ink' => $run['hours'] !== null, 'text-subtle' => $run['hours'] === null])>
                @if ($run['hours'] === null)
                    {{ __('rates.closed') }}
                @else
                    {{ $run['hours'][0] }} &ndash; {{ $run['hours'][1] }}
                @endif
            </dd>
        @endforeach
    </dl>
@endif
