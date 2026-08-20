@props(['request', 'compact' => false])

@php
    use App\Models\QuoteRequest;

    // The preferences the traveler actually narrowed. "Any" and "flexible"
    // are left out on purpose - a brief that lists every unstated
    // preference back at the reader is longer and says less.
    $preferences = collect([
        $request->flight_preference !== QuoteRequest::FLIGHT_FLEXIBLE
            ? __('tourism.flights.' . $request->flight_preference) : null,
        $request->hotel_preference !== QuoteRequest::HOTEL_ANY
            ? __('tourism.hotel_class.' . $request->hotel_preference) : null,
        $request->meal_preference !== QuoteRequest::MEAL_ANY
            ? __('tourism.meals.' . $request->meal_preference) : null,
        $request->insurance ? __('tourism.request.insurance') : null,
    ])->filter();

    // A request may name several destinations, or none at all when the
    // traveller is open to suggestions - so both the heading and the flag
    // come off the list rather than the single destination_country column,
    // which is null in that case.
    $destinations = $request->destinations;
    $destinationName = $destinations === []
        ? __('tourism.request.summary_open_to_suggestions')
        : implode(', ', $request->destination_labels);

    // The flag of the first destination. Regional indicator symbols are
    // just the two ISO letters shifted into a Unicode block, so this is
    // correct for any country without a lookup table.
    $flag = $destinations === []
        ? '🌍'
        : mb_chr(127462 + (ord($destinations[0][0]) - 65)) . mb_chr(127462 + (ord($destinations[0][1]) - 65));
@endphp

<div {{ $attributes }}>
    <div class="flex items-start gap-4">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/5 text-2xl">{{ $flag }}</span>

        <div class="min-w-0 flex-1">
            <h2 class="font-heading text-lg font-bold text-ink">{{ $destinationName }}</h2>

            <p class="mt-1 text-sm text-muted">
                {{ $request->check_in->translatedFormat('d M') }} – {{ $request->check_out->translatedFormat('d M Y') }}
                · {{ trans_choice('tourism.brief.nights', $request->nights, ['count' => $request->nights]) }}
            </p>

            <p class="mt-0.5 text-sm text-muted">
                {{ trans_choice('tourism.brief.adults', $request->adults, ['count' => $request->adults]) }}@if ($request->children > 0), {{ trans_choice('tourism.brief.children', $request->children, ['count' => $request->children]) }}@endif
            </p>

            @if ($request->departure_location)
                <p class="mt-0.5 text-sm text-muted">{{ __('tourism.request.summary_from', ['location' => $request->departure_location]) }}</p>
            @endif

            @if ($request->has_flexible_dates)
                <p class="mt-0.5 text-sm text-muted">
                    {{ __('tourism.date_flexibility.' . $request->date_flexibility) }}
                </p>
            @endif
        </div>
    </div>

    @unless ($compact)
        @if ($request->hotel_name)
            <p class="mt-4 text-sm text-ink">
                <span class="text-subtle">{{ __('tourism.request.hotel_name') }}:</span> {{ $request->hotel_name }}
            </p>
        @endif

        @if ($preferences->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-1.5">
                @foreach ($preferences as $preference)
                    <span class="rounded-full bg-primary/5 px-2.5 py-1 text-xs font-medium text-primary ring-1 ring-primary/20">{{ $preference }}</span>
                @endforeach
            </div>
        @endif

        @if ($request->priority_labels !== [])
            <div class="mt-3">
                <p class="text-xs text-subtle">{{ __('tourism.request.priorities_label') }}</p>
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @foreach ($request->priority_labels as $priority)
                        <span class="rounded-full bg-accent-yellow/20 px-2.5 py-1 text-xs font-medium text-ink">{{ $priority }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($request->budget_min_amd || $request->budget_max_amd)
            <p class="mt-4 border-t border-placeholder pt-3 text-sm text-ink">
                <span class="text-subtle">{{ __('tourism.request.summary_budget') }}:</span>
                @if ($request->budget_min_amd && $request->budget_max_amd)
                    {{ number_format((float) $request->budget_min_amd) }} – {{ number_format((float) $request->budget_max_amd) }} {{ __('tourism.request.amd') }}
                @elseif ($request->budget_min_amd)
                    {{ __('tourism.request.summary_budget_from') }} {{ number_format((float) $request->budget_min_amd) }} {{ __('tourism.request.amd') }}
                @else
                    {{ __('tourism.telegram.budget_up_to', ['amount' => number_format((float) $request->budget_max_amd)]) }}
                @endif
            </p>
        @endif

        @if ($request->notes)
            <p class="mt-3 rounded-xl bg-placeholder/20 px-4 py-3 text-sm leading-relaxed text-ink">{{ $request->notes }}</p>
        @endif
    @endunless
</div>
