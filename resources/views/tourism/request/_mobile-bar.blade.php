
<div class="sticky bottom-0 z-30 mt-6 border-t border-border bg-white/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur md:hidden">
    <div class="flex items-center gap-3">
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-ink" x-text="compactSummary"></p>
            <a href="#travel-request-summary" class="text-xs font-bold text-primary hover:underline">
                {{ __('tourism.request.review_request') }}
            </a>
        </div>

        <button
            type="button"
            x-show="step < totalSteps"
            @click="step === 1 ? toContact() : next()"
            class="btn btn-primary shrink-0"
        >
            {{ __('tourism.request.wizard_continue') }}
            <x-travel-icon name="arrow_forward" class="h-4 w-4" />
        </button>

        <button
            type="submit"
            x-show="step === totalSteps"
            x-cloak
            :disabled="!consented"
            class="btn btn-primary shrink-0 disabled:cursor-not-allowed disabled:opacity-50"
        >
            {{ __('tourism.request.submit_offers_short') }}
            <x-travel-icon name="arrow_forward" class="h-4 w-4" />
        </button>
    </div>
</div>
