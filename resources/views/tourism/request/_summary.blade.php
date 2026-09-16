@php
    $rows = [
        ['label' => __('tourism.request.summary_destination'), 'value' => 'destinationSummary', 'icon' => 'location_on'],
        ['label' => __('tourism.request.summary_dates'), 'value' => 'datesSummary', 'icon' => 'calendar_month'],
        ['label' => __('tourism.request.summary_travelers'), 'value' => 'travellersSummary', 'icon' => 'group'],
        ['label' => __('tourism.request.summary_flight'), 'value' => 'flightSummary', 'icon' => 'flight'],
        ['label' => __('tourism.request.summary_hotel'), 'value' => 'hotelSummary', 'icon' => 'hotel'],
        ['label' => __('tourism.request.summary_meals'), 'value' => 'mealsSummary', 'icon' => 'restaurant'],
    ];
@endphp

{{-- What you have told us so far, in the rates summary card. --}}
<div id="travel-request-summary" class="scroll-mt-24 rounded-2xl border border-border bg-surface p-5 sm:p-6 lg:sticky lg:top-24">
    <div class="flex items-center justify-between gap-3">
        <span class="text-xs font-semibold tracking-wider text-muted uppercase">{{ __('tourism.request.summary_heading_request') }}</span>
        <button type="button" x-show="step > 1" x-cloak @click="goToStep(1)" class="shrink-0 text-sm font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:outline-none">
            {{ __('tourism.request.summary_edit') }}
        </button>
    </div>

    <div x-show="hasItinerary" x-cloak class="mt-4">
        <p class="text-lg font-bold text-ink" x-text="itineraryRoute"></p>
        <p class="mt-0.5 text-sm text-muted" x-text="itineraryMeta"></p>
    </div>

    <dl class="mt-4 space-y-3 border-t border-border pt-4 text-sm">
        @foreach ($rows as $row)
            <div class="flex items-center justify-between gap-3">
                <dt class="flex shrink-0 items-center gap-2 text-muted">
                    <x-travel-icon :name="$row['icon']" class="h-4 w-4 text-primary" />
                    {{ $row['label'] }}
                </dt>
                <dd class="text-right font-semibold text-ink" x-text="{{ $row['value'] }}"></dd>
            </div>
        @endforeach

        <div class="flex items-center justify-between gap-3">
            <dt class="flex shrink-0 items-center gap-2 text-muted">
                <x-travel-icon name="wallet" class="h-4 w-4 text-primary" />
                {{ __('tourism.request.summary_budget') }}
            </dt>
            <dd
                class="text-right font-semibold text-ink"
                :class="budgetBand || budgetMin || budgetMax ? '' : 'font-normal text-subtle'"
                x-text="budgetSummary"
            ></dd>
        </div>

        <div x-show="priorities.length" x-cloak class="border-t border-border pt-3">
            <dt class="mb-2 text-muted">{{ __('tourism.request.priorities_label') }}</dt>
            <dd class="flex flex-wrap gap-1.5">
                <template x-for="value in priorities" :key="value">
                    <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary" x-text="@js($priorityOptions)[value]"></span>
                </template>
            </dd>
        </div>
    </dl>
</div>
