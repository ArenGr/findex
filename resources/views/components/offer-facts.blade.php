@props(['offer', 'request' => null])

@php
    use App\Models\QuoteSuggestion;

    // A yes/no the agency stated, or "not stated" when it didn't.
    $flag = function (?bool $value): array {
        return match ($value) {
            true => ['label' => __('tourism.offer.included'), 'class' => 'text-primary'],
            false => ['label' => __('tourism.offer.not_included'), 'class' => 'text-muted'],
            default => ['label' => __('tourism.offer.not_stated'), 'class' => 'text-subtle'],
        };
    };

    $flightLabel = match (true) {
        $offer->flight_included === false => __('tourism.offer.flights_not_included'),
        $offer->flight_type !== null => __('tourism.flight_types.' . $offer->flight_type),
        $offer->flight_included === true => __('tourism.offer.flights_included_unspecified'),
        default => __('tourism.offer.not_stated'),
    };
@endphp

<dl {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3']) }}>
    <div>
        <dt class="text-xs tracking-wide text-subtle uppercase">{{ __('tourism.offer.hotel') }}</dt>
        <dd class="mt-1 text-sm font-medium text-ink">
            {{ $offer->offered_hotel_name ?: __('tourism.offer.not_stated') }}
            @if ($offer->hotel_stars)
                <span class="mt-1 block">
                    <x-star-rating :rating="$offer->hotel_stars" size="h-3 w-3" />
                </span>
            @endif
        </dd>
    </div>

    <div>
        <dt class="text-xs tracking-wide text-subtle uppercase">{{ __('tourism.offer.flights') }}</dt>
        <dd class="mt-1 text-sm font-medium text-ink">{{ $flightLabel }}</dd>
    </div>

    <div>
        <dt class="text-xs tracking-wide text-subtle uppercase">{{ __('tourism.offer.meals') }}</dt>
        <dd class="mt-1 text-sm font-medium text-ink">
            {{ $offer->meal_plan ? __('tourism.meals.' . $offer->meal_plan) : __('tourism.offer.not_stated') }}
        </dd>
    </div>

    <div>
        <dt class="text-xs tracking-wide text-subtle uppercase">{{ __('tourism.offer.transfer') }}</dt>
        <dd class="mt-1 text-sm font-medium {{ $flag($offer->transfer_included)['class'] }}">
            {{ $flag($offer->transfer_included)['label'] }}
        </dd>
    </div>

    <div>
        <dt class="text-xs tracking-wide text-subtle uppercase">{{ __('tourism.offer.insurance') }}</dt>
        <dd class="mt-1 text-sm font-medium {{ $flag($offer->insurance_included)['class'] }}">
            {{ $flag($offer->insurance_included)['label'] }}
        </dd>
    </div>

    @if ($request)
        <div>
            <dt class="text-xs tracking-wide text-subtle uppercase">{{ __('tourism.offer.dates') }}</dt>
            <dd class="mt-1 text-sm font-medium text-ink">
                {{ $request->check_in->translatedFormat('d M') }} – {{ $request->check_out->translatedFormat('d M') }}
            </dd>
        </div>
    @endif
</dl>

@if ($offer->flight_details || $offer->inclusions)
    <div class="mt-4 space-y-2 border-t border-placeholder pt-3">
        @if ($offer->flight_details)
            <p class="text-sm text-ink"><span class="text-subtle">{{ __('tourism.results.flight_label') }}:</span> {{ $offer->flight_details }}</p>
        @endif
        @if ($offer->inclusions)
            <p class="text-sm text-ink"><span class="text-subtle">{{ __('tourism.results.inclusions_label') }}:</span> {{ $offer->inclusions }}</p>
        @endif
    </div>
@endif
