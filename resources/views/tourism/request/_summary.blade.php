@php
    use App\Support\TravelHero;

    // Every row derives from the same Alpine state the form submits - see
    // resources/js/travel-request-form.js. Nothing here holds its own copy, so
    // the summary cannot drift from what is about to be sent. In the stepped
    // flow this panel is read-only: consent and submit live on the final step.
    //
    // One glyph per row, so a row is recognisable before it is read.
    $rows = [
        ['label' => __('tourism.request.summary_destination'), 'value' => 'destinationSummary', 'icon' => 'location_on'],
        ['label' => __('tourism.request.summary_dates'), 'value' => 'datesSummary', 'icon' => 'calendar_month'],
        ['label' => __('tourism.request.summary_travelers'), 'value' => 'travellersSummary', 'icon' => 'group'],
        ['label' => __('tourism.request.summary_flight'), 'value' => 'flightSummary', 'icon' => 'flight'],
        ['label' => __('tourism.request.summary_hotel'), 'value' => 'hotelSummary', 'icon' => 'hotel'],
        ['label' => __('tourism.request.summary_meals'), 'value' => 'mealsSummary', 'icon' => 'restaurant'],
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
     the fields it is summarising have scrolled past. The tip below it is a
     card of its own rather than a panel inside the panel. --}}
<div id="travel-request-summary" class="scroll-mt-24 space-y-4 lg:sticky lg:top-24">
    <div class="space-y-6 rounded-2xl border border-gray-200/80 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <div class="flex min-w-0 items-center gap-2.5">
                <span class="rounded-lg bg-travel-100 p-2 text-travel-700">
                    <x-travel-icon name="luggage" class="h-4 w-4" />
                </span>
                <span class="min-w-0">
                    <span class="block text-sm leading-none font-bold text-gray-900">{{ __('tourism.request.summary_heading_request') }}</span>
                    <span class="mt-1 block text-[11px] text-gray-400">{{ __('tourism.request.summary_live') }}</span>
                </span>
            </div>
            <button type="button" x-show="step > 1" x-cloak @click="goToStep(1)" class="shrink-0 text-xs font-bold text-travel-600 hover:text-travel-700 hover:underline focus-visible:ring-2 focus-visible:ring-travel-600/40 focus-visible:outline-none">
                {{ __('tourism.request.summary_edit') }}
            </button>
        </div>

        {{-- Nothing entered yet. --}}
        <div x-show="!hasAnyDetail" @if ($hasDetail) x-cloak @endif class="text-center">
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
                    class="mx-auto w-[180px] max-w-full"
                >
            @endif
            <p class="mt-2 text-sm font-bold text-gray-900">{{ __('tourism.request.summary_empty_title') }}</p>
            <p class="mx-auto mt-1.5 max-w-[17rem] text-[11px] leading-relaxed text-gray-500">{{ __('tourism.request.summary_empty_body') }}</p>
        </div>

        {{-- ...and what it becomes once there is something to show. --}}
        <div x-show="hasAnyDetail" @if (! $hasDetail) x-cloak @endif class="space-y-6">
            <div x-show="hasItinerary" x-cloak class="space-y-1">
                <p class="text-base font-bold text-gray-900" x-text="itineraryRoute"></p>
                <p class="text-xs text-gray-500" x-text="itineraryMeta"></p>
            </div>

            <dl class="space-y-3.5 text-xs text-gray-600">
                @foreach ($rows as $row)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="flex shrink-0 items-center gap-2 text-gray-500">
                            <x-travel-icon :name="$row['icon']" class="h-4 w-4 text-travel-600" />
                            {{ $row['label'] }}
                        </dt>
                        <dd class="text-right font-semibold text-gray-900" x-text="{{ $row['value'] }}"></dd>
                    </div>
                @endforeach

                {{-- Budget sits below a rule: it is the one row that is still
                     blank by the time the other six are answered. --}}
                <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-3">
                    <dt class="flex shrink-0 items-center gap-2 text-gray-500">
                        <x-travel-icon name="wallet" class="h-4 w-4 text-travel-600" />
                        {{ __('tourism.request.summary_budget') }}
                    </dt>
                    <dd
                        class="text-right font-semibold text-gray-900"
                        :class="budgetBand || budgetMin || budgetMax ? '' : 'text-xs font-normal text-gray-400 italic'"
                        x-text="budgetSummary"
                    ></dd>
                </div>

                <div x-show="priorities.length" x-cloak class="border-t border-gray-100 pt-3">
                    <dt class="mb-2 text-gray-500">{{ __('tourism.request.priorities_label') }}</dt>
                    <dd class="flex flex-wrap gap-1.5">
                        <template x-for="value in priorities" :key="value">
                            <span
                                class="rounded-full border border-travel-200 bg-travel-50 px-2.5 py-1 text-[11px] font-semibold text-travel-700"
                                x-text="@js($priorityOptions)[value]"
                            ></span>
                        </template>
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Says what the panel above is for: the rows are only worth filling in
         because fuller answers come back as better offers. --}}
    <div class="flex items-start gap-3 rounded-2xl border border-travel-200/80 bg-travel-50/70 p-4">
        <x-travel-icon name="lightbulb" class="mt-0.5 h-5 w-5 text-travel-600" />
        <span class="min-w-0">
            <span class="block text-xs font-bold text-travel-800">{{ __('tourism.request.summary_tip_title') }}</span>
            <span class="mt-0.5 block text-[11px] leading-snug text-travel-700">{{ __('tourism.request.summary_tip_body') }}</span>
        </span>
    </div>
</div>
