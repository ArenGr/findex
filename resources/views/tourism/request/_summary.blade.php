@php
    // Every row derives from the same Alpine state the form submits - see
    // resources/js/travel-request-form.js. Nothing here holds its own copy,
    // so the summary cannot drift from what is about to be sent.
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

<div id="travel-request-summary" class="scroll-mt-24 rounded-xl border border-travel-primary/20 bg-surface-alt p-5 sm:p-6 md:sticky md:top-24">
    <h2 class="mb-4 border-b border-border-subtle pb-4 text-headline-md text-on-surface">
        {{ __('tourism.request.summary_heading_request') }}
    </h2>

    <dl class="mb-6 flex flex-col gap-3">
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

    <button
        type="submit"
        class="mb-3 flex w-full items-center justify-center gap-2 rounded-lg bg-travel-primary px-6 py-4 text-label-caps font-bold text-white transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-travel-primary/40 focus-visible:outline-none"
    >
        {{ __('tourism.request.submit_offers') }}
        <x-travel-icon name="arrow_forward" class="h-[18px] w-[18px]" />
    </button>

    <div class="mb-6 flex flex-col items-center gap-1">
        <p class="text-label-caps text-ink-muted">{{ __('tourism.request.free_no_commitment') }}</p>
        <p class="flex items-center gap-1 text-center text-label-caps text-ink-muted">
            <x-travel-icon name="lock" class="h-3.5 w-3.5 shrink-0" />
            {{ __('tourism.request.shared_with_agencies') }}
        </p>
    </div>

    {{-- Consent isn't in the design, which assumes it handled elsewhere -
         but the trip details are about to be sent to third-party agencies,
         so it is asked for here, next to the action that does it. --}}
    <label class="mb-4 flex items-start gap-2 text-body-sm text-on-surface">
        <input type="checkbox" name="consent" value="1" required class="mt-1 h-4 w-4 shrink-0 rounded border-border-subtle text-travel-primary focus:ring-travel-primary">
        <span>{{ __('tourism.request.consent') }}</span>
    </label>
    @error('consent')
        <p class="mb-4 text-body-sm text-error">{{ $message }}</p>
    @enderror

    <div class="rounded-lg border border-travel-primary/10 bg-travel-primary/5 p-4">
        <h3 class="mb-2 flex items-center gap-2 text-label-caps font-bold text-on-surface">
            <x-travel-icon name="info" class="h-4 w-4 text-travel-primary" />
            {{ __('tourism.request.next_heading') }}
        </h3>
        <p class="text-body-sm leading-relaxed text-ink-muted">{{ __('tourism.request.next_body') }}</p>
    </div>
</div>
