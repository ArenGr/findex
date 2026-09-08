@php
    use App\Support\TravelHero;

    // Every row derives from the same Alpine state the form submits - see
    // resources/js/travel-request-form.js. Nothing here holds its own copy, so
    // the summary cannot drift from what is about to be sent. In the stepped
    // flow this panel is read-only: consent and submit live on the final step.
    //
    // Icons are the design pack's, one per row, so a row is recognisable
    // before it is read.
    $rows = [
        ['label' => __('tourism.request.summary_destination'), 'value' => 'destinationSummary', 'icon' => 'icon-location'],
        ['label' => __('tourism.request.summary_dates'), 'value' => 'datesSummary', 'icon' => 'icon-calendar'],
        ['label' => __('tourism.request.summary_travelers'), 'value' => 'travellersSummary', 'icon' => 'icon-travelers'],
        ['label' => __('tourism.request.summary_flight'), 'value' => 'flightSummary', 'icon' => 'icon-plane-row'],
        ['label' => __('tourism.request.summary_hotel'), 'value' => 'hotelSummary', 'icon' => 'icon-hotel'],
        ['label' => __('tourism.request.summary_meals'), 'value' => 'mealsSummary', 'icon' => 'icon-meals'],
        ['label' => __('tourism.request.summary_budget'), 'value' => 'budgetSummary', 'icon' => 'icon-budget'],
    ];

    $emptyArt = TravelHero::asset('trip-empty');

    // The server's answer to Alpine's hasAnyDetail, so the branch that paints
    // first is the branch that stays. Cloaking both left the panel blank until
    // Alpine booted; cloaking neither showed both for a frame.
    $hasDetail = (bool) (
        trim((string) old('departure_location', ''))
        || array_filter((array) old('destination_countries', []))
        || old('open_to_suggestions')
        || (old('check_in') && old('check_out'))
        || old('budget_band')
        || old('budget_min_amd')
        || old('budget_max_amd')
        || array_filter((array) old('priorities', []))
    );
@endphp

{{-- What you have told us so far. Sticky on desktop, so it is still there when
     the fields it is summarising have scrolled past. --}}
<div
    id="travel-request-summary"
    class="scroll-mt-24 rounded-[16px] bg-travel-cream p-4 md:sticky md:top-5 lg:p-5"
>
    <div class="flex items-start justify-between gap-2">
        <div class="flex min-w-0 items-center gap-2.5">
            <img src="{{ asset('images/travel/svg/icon-suitcase.svg') }}" alt="" aria-hidden="true" width="200" height="200" class="h-8 w-8 shrink-0">
            <span class="min-w-0">
                <span class="block text-[17px] leading-5 font-bold text-travel-ink">{{ __('tourism.request.summary_heading_request') }}</span>
                <span class="mt-0.5 block text-[12px] leading-4 text-travel-muted">{{ __('tourism.request.summary_live') }}</span>
            </span>
        </div>
        <button type="button" x-show="step > 1" x-cloak @click="goToStep(1)" class="shrink-0 text-[13px] font-medium text-travel-green hover:underline focus-visible:ring-2 focus-visible:ring-travel-green/40 focus-visible:outline-none">
            {{ __('tourism.request.summary_edit') }}
        </button>
    </div>

    {{-- Nothing entered yet. --}}
    <div x-show="!hasAnyDetail" @if ($hasDetail) x-cloak @endif class="pt-3 pb-1 text-center">
        @if ($emptyArt)
            <img
                src="{{ $emptyArt['src'] }}"
                @isset($emptyArt['srcset']['webp'])
                    srcset="{{ $emptyArt['srcset']['webp'] }}"
                    sizes="230px"
                @endisset
                alt=""
                aria-hidden="true"
                width="{{ $emptyArt['width'] }}"
                height="{{ $emptyArt['height'] }}"
                decoding="async"
                class="mx-auto w-[195px] max-w-full"
            >
        @endif
        <p class="mt-2 text-[15px] font-bold text-travel-ink">{{ __('tourism.request.summary_empty_title') }}</p>
        <p class="mx-auto mt-1.5 max-w-[17rem] text-[13px] leading-5 text-travel-muted">{{ __('tourism.request.summary_empty_body') }}</p>
    </div>

    {{-- ...and what it becomes once there is something to show. --}}
    <div x-show="hasAnyDetail" @if (! $hasDetail) x-cloak @endif>
        <div x-show="hasItinerary" x-cloak class="mt-5 rounded-xl bg-white p-4">
            <p class="text-[15px] font-semibold text-travel-ink" x-text="itineraryRoute"></p>
            <p class="mt-1 text-[13px] text-travel-muted" x-text="itineraryMeta"></p>
        </div>

        <dl class="mt-5 flex flex-col gap-3 border-t border-travel-border pt-4">
            @foreach ($rows as $row)
                <div class="flex items-center justify-between gap-3 text-[13px]">
                    <dt class="flex shrink-0 items-center gap-2 text-travel-muted">
                        <img src="{{ asset('images/travel/svg/' . $row['icon'] . '.svg') }}" alt="" aria-hidden="true" width="200" height="200" class="h-4 w-4">
                        {{ $row['label'] }}
                    </dt>
                    <dd class="text-right font-semibold text-travel-ink" x-text="{{ $row['value'] }}"></dd>
                </div>
            @endforeach

            <div x-show="priorities.length" x-cloak class="border-t border-travel-border pt-3">
                <dt class="mb-1.5 text-[13px] text-travel-muted">{{ __('tourism.request.priorities_label') }}</dt>
                <dd class="flex flex-wrap gap-1.5">
                    <template x-for="value in priorities" :key="value">
                        <span
                            class="rounded-full bg-travel-sage px-2.5 py-1 text-[11px] font-semibold text-travel-green"
                            x-text="@js($priorityOptions)[value]"
                        ></span>
                    </template>
                </dd>
            </div>
        </dl>
    </div>

    {{-- Says what the panel above is for: the rows are only worth filling in
         because fuller answers come back as better offers. --}}
    <div class="mt-4 flex gap-2.5 rounded-[12px] bg-travel-sage p-3.5">
        <svg class="mt-px h-4 w-4 shrink-0 text-travel-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 18h6" /><path d="M10 22h4" />
            <path d="M15.1 14a5 5 0 1 0-6.2 0c.5.4.8 1 .9 1.6h4.4c.1-.6.4-1.2.9-1.6z" />
        </svg>
        <span class="min-w-0">
            <span class="block text-[13px] leading-4 font-semibold text-travel-ink">{{ __('tourism.request.summary_tip_title') }}</span>
            <span class="mt-1 block text-[12px] leading-[1.45] text-travel-muted">{{ __('tourism.request.summary_tip_body') }}</span>
        </span>
    </div>
</div>
