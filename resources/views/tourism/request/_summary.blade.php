@php
    // Every row derives from the same Alpine state the form submits - see
    // resources/js/travel-request-form.js. Nothing here holds its own copy,
    // so the summary cannot drift from what is about to be sent. In the
    // stepped flow this panel is read-only: consent and the submit button
    // live on the final "Review & send" step, not here.
    $rows = [
        ['label' => __('tourism.request.summary_destination'), 'value' => 'destinationSummary'],
        ['label' => __('tourism.request.summary_dates'), 'value' => 'datesSummary'],
        ['label' => __('tourism.request.summary_travelers'), 'value' => 'travellersSummary'],
        ['label' => __('tourism.request.summary_flight'), 'value' => 'flightSummary'],
        ['label' => __('tourism.request.summary_hotel'), 'value' => 'hotelSummary'],
        ['label' => __('tourism.request.summary_meals'), 'value' => 'mealsSummary'],
        ['label' => __('tourism.request.summary_budget'), 'value' => 'budgetSummary'],
    ];
@endphp

<div
    id="travel-request-summary"
    class="scroll-mt-24 rounded-[15px] border border-border-subtle bg-white p-5 shadow-[0_3px_14px_rgba(24,29,18,0.035)] md:sticky md:top-5"
>
    <div class="mb-4 flex items-start justify-between gap-2 border-b border-border-subtle pb-4">
        <div class="min-w-0">
            <h2 class="text-headline-sm font-semibold text-on-surface">{{ __('tourism.request.summary_heading_request') }}</h2>
            <p class="mt-1 text-[13px] leading-5 text-ink-muted">{{ __('tourism.request.summary_live') }}</p>
        </div>
        <button type="button" x-show="step > 1" x-cloak @click="goToStep(1)" class="shrink-0 text-[13px] font-medium text-travel-primary hover:underline focus-visible:ring-2 focus-visible:ring-travel-primary/40 focus-visible:outline-none">
            {{ __('tourism.request.summary_edit') }}
        </button>
    </div>

    <div x-show="hasItinerary" x-cloak class="mb-5 rounded-lg border border-travel-primary/15 bg-travel-primary/5 p-4">
        <p class="text-body-md font-semibold text-on-surface" x-text="itineraryRoute"></p>
        <p class="mt-1 text-body-sm text-ink-muted" x-text="itineraryMeta"></p>
    </div>

    <dl class="flex flex-col gap-3">
        @foreach ($rows as $row)
            <div class="flex justify-between gap-3 text-body-sm">
                <dt class="shrink-0 text-ink-muted">{{ $row['label'] }}</dt>
                <dd class="text-right font-semibold text-on-surface" x-text="{{ $row['value'] }}"></dd>
            </div>
        @endforeach

        <div x-show="priorities.length" x-cloak class="border-t border-border-subtle pt-3">
            <dt class="mb-1.5 text-body-sm text-ink-muted">{{ __('tourism.request.priorities_label') }}</dt>
            <dd class="flex flex-wrap gap-1.5">
                <template x-for="value in priorities" :key="value">
                    <span
                        class="rounded-full bg-travel-primary/10 px-2.5 py-1 text-label-caps text-travel-primary"
                        x-text="@js($priorityOptions)[value]"
                    ></span>
                </template>
            </dd>
        </div>
    </dl>
</div>
