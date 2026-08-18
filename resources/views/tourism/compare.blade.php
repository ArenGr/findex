@extends('layouts.app')

@section('title', __('tourism.compare.heading') . ' — Findex')

@php
    use App\Models\QuoteRequest;
    use App\Models\QuoteSuggestion;

    /**
     * The rows of the comparison, defined once and rendered twice - as
     * table rows on desktop and as a labelled list inside each card on
     * mobile. Two hand-maintained copies of this list is how a column
     * quietly goes missing from one of the two layouts.
     */
    $flagLabel = fn (?bool $value) => match ($value) {
        true => __('tourism.offer.included'),
        false => __('tourism.offer.not_included'),
        default => __('tourism.offer.not_stated'),
    };

    $rows = [
        [
            'label' => __('tourism.compare.row_hotel'),
            'value' => fn ($row) => $row['offer']->offered_hotel_name ?: __('tourism.offer.not_stated'),
        ],
        [
            'label' => __('tourism.compare.row_stars'),
            'value' => fn ($row) => $row['offer']->hotel_stars
                ? $row['offer']->hotel_stars . '★'
                : __('tourism.offer.not_stated'),
        ],
        [
            'label' => __('tourism.compare.row_meals'),
            'value' => fn ($row) => $row['offer']->meal_plan
                ? __('tourism.meals.' . $row['offer']->meal_plan)
                : __('tourism.offer.not_stated'),
        ],
        [
            'label' => __('tourism.compare.row_flight'),
            'value' => fn ($row) => match (true) {
                $row['offer']->flight_included === false => __('tourism.offer.flights_not_included'),
                $row['offer']->flight_type !== null => __('tourism.flight_types.' . $row['offer']->flight_type),
                $row['offer']->flight_included === true => __('tourism.offer.flights_included_unspecified'),
                default => __('tourism.offer.not_stated'),
            },
        ],
        [
            'label' => __('tourism.compare.row_transfer'),
            'value' => fn ($row) => $flagLabel($row['offer']->transfer_included),
        ],
        [
            'label' => __('tourism.compare.row_insurance'),
            'value' => fn ($row) => $flagLabel($row['offer']->insurance_included),
        ],
        [
            'label' => __('tourism.compare.row_valid_until'),
            'value' => fn ($row) => $row['response']->valid_until
                ? $row['response']->valid_until->translatedFormat('d M, H:i')
                : '—',
        ],
    ];

    $price = fn ($row) => rtrim(rtrim((string) $row['offer']->price_amount, '0'), '.') . ' ' . $row['offer']->price_currency;
@endphp

@section('content')
    <section class="mx-auto max-w-6xl px-6 py-16 lg:px-10">
        <a href="{{ $offersUrl }}" class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-primary">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path fill-rule="evenodd" d="M12.7 15.7a1 1 0 0 1-1.4 0l-5-5a1 1 0 0 1 0-1.4l5-5a1 1 0 1 1 1.4 1.4L8.4 10l4.3 4.3a1 1 0 0 1 0 1.4Z" clip-rule="evenodd" />
            </svg>
            {{ __('tourism.compare.back_to_offers') }}
        </a>

        <h1 class="mt-6 font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('tourism.compare.heading') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('tourism.compare.subheading') }}</p>

        <div class="mt-6 rounded-2xl border border-placeholder bg-white p-5 shadow-sm">
            <x-trip-brief :request="$quoteRequest" compact />
        </div>

        @if ($selected->count() < 2)
            <div class="mt-8 rounded-2xl border border-dashed border-placeholder p-10 text-center">
                <p class="text-sm text-muted">{{ __('tourism.compare.empty') }}</p>
                <a href="{{ $offersUrl }}" class="mt-4 inline-block bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-dark">
                    {{ __('tourism.compare.back_to_offers') }}
                </a>
            </div>
        @else
            {{-- Mobile: one full-width card per offer, swiped horizontally
            with scroll snapping. Deliberately NOT the desktop table scaled
            down - four columns squeezed into a phone leaves each one too
            narrow to read a hotel name in, which is the one thing the
            comparison exists to show. --}}
            <div class="mt-8 lg:hidden">
                <p class="mb-3 text-xs text-subtle">{{ __('tourism.compare.mobile_hint') }}</p>

                <div class="-mx-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-6 pb-4">
                    @foreach ($selected as $row)
                        <div class="w-[85vw] max-w-sm shrink-0 snap-center rounded-2xl border border-placeholder bg-white p-5 shadow-sm">
                            <div class="flex items-center gap-3">
                                @if ($row['organization']->logo)
                                    <img src="{{ $row['organization']->logo }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-contain">
                                @else
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-xs font-bold text-white">
                                        {{ Str::of($row['organization']->name)->substr(0, 2)->upper() }}
                                    </span>
                                @endif
                                <p class="min-w-0 truncate font-semibold text-ink">{{ $row['organization']->name }}</p>
                            </div>

                            <p class="mt-4 font-heading text-2xl font-bold text-primary">{{ $price($row) }}</p>

                            @if (in_array('lowest_price', $row['badges'], true))
                                <span class="mt-1 inline-block rounded-full bg-accent-yellow/30 px-2.5 py-0.5 text-xs font-semibold text-ink">
                                    {{ __('tourism.offers.badge_lowest_price') }}
                                </span>
                            @endif

                            <dl class="mt-4 divide-y divide-placeholder border-t border-placeholder">
                                @foreach ($rows as $definition)
                                    <div class="flex items-start justify-between gap-4 py-2.5">
                                        <dt class="text-xs tracking-wide text-subtle uppercase">{{ $definition['label'] }}</dt>
                                        <dd class="text-right text-sm text-ink">{{ $definition['value']($row) }}</dd>
                                    </div>
                                @endforeach
                            </dl>

                            <a
                                href="{{ $quoteRequest->signedUrlFor('tourism.offers.show', ['suggestion' => $row['offer']->id]) }}"
                                class="mt-4 block bg-primary px-4 py-2 text-center text-sm font-medium text-white hover:bg-primary-dark"
                            >
                                {{ __('tourism.offers.view_details') }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Desktop: a real side-by-side table. --}}
            <div class="mt-8 hidden overflow-x-auto rounded-2xl border border-placeholder lg:block">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr>
                            <th scope="col" class="w-40 bg-placeholder/10 px-4 py-4 text-left">
                                <span class="sr-only">{{ __('tourism.compare.row_agency') }}</span>
                            </th>
                            @foreach ($selected as $row)
                                <th scope="col" class="border-b border-placeholder bg-placeholder/10 px-4 py-4 text-left align-bottom">
                                    <div class="flex items-center gap-2">
                                        @if ($row['organization']->logo)
                                            <img src="{{ $row['organization']->logo }}" alt="" class="h-8 w-8 shrink-0 rounded-full object-contain">
                                        @else
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-white">
                                                {{ Str::of($row['organization']->name)->substr(0, 2)->upper() }}
                                            </span>
                                        @endif
                                        <span class="font-semibold text-ink">{{ $row['organization']->name }}</span>
                                    </div>

                                    @if (in_array('lowest_price', $row['badges'], true))
                                        <span class="mt-2 inline-block rounded-full bg-accent-yellow/30 px-2 py-0.5 text-xs font-semibold text-ink">
                                            {{ __('tourism.offers.badge_lowest_price') }}
                                        </span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <th scope="row" class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                {{ __('tourism.compare.row_price') }}
                            </th>
                            @foreach ($selected as $row)
                                <td class="border-t border-placeholder px-4 py-4">
                                    <span class="font-heading text-lg font-bold text-primary">{{ $price($row) }}</span>
                                </td>
                            @endforeach
                        </tr>

                        @foreach ($rows as $definition)
                            <tr>
                                <th scope="row" class="px-4 py-4 text-left text-xs font-semibold tracking-wider text-subtle uppercase">
                                    {{ $definition['label'] }}
                                </th>
                                @foreach ($selected as $row)
                                    <td class="border-t border-placeholder px-4 py-4 text-ink">{{ $definition['value']($row) }}</td>
                                @endforeach
                            </tr>
                        @endforeach

                        <tr>
                            <th scope="row" class="px-4 py-4 text-left"><span class="sr-only">{{ __('tourism.offers.view_details') }}</span></th>
                            @foreach ($selected as $row)
                                <td class="border-t border-placeholder px-4 py-4">
                                    <a
                                        href="{{ $quoteRequest->signedUrlFor('tourism.offers.show', ['suggestion' => $row['offer']->id]) }}"
                                        class="inline-block bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark"
                                    >
                                        {{ __('tourism.offers.view_details') }}
                                    </a>
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
