
{{-- z-30: the wizard card above is `relative z-20`, and this bar is
     positioned too but had no z-index of its own - so wherever the card
     overlapped where the bar sticks, which on a phone is most of the way
     down the form, the card's white background was painted over it and the
     bar was simply not there. --}}
<div class="sticky bottom-0 z-30 mt-6 border-t border-gray-200 bg-white/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur md:hidden">
    <div class="flex items-center gap-3">
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-gray-900" x-text="compactSummary"></p>
            <a href="#travel-request-summary" class="text-xs font-bold text-travel-600 hover:underline">
                {{ __('tourism.request.review_request') }}
            </a>
        </div>

        <button
            type="button"
            x-show="step < totalSteps"
            @click="next()"
            class="flex shrink-0 items-center gap-1.5 rounded-lg bg-travel-600 px-5 py-3 text-sm font-bold text-white shadow-md transition-colors hover:bg-travel-700 focus-visible:ring-2 focus-visible:ring-travel-600/40 focus-visible:outline-none"
        >
            {{ __('tourism.request.wizard_continue') }}
            <x-travel-icon name="arrow_forward" class="h-4 w-4" />
        </button>

        <button
            type="submit"
            x-show="step === totalSteps"
            x-cloak
            :disabled="!consented"
            class="flex shrink-0 items-center gap-1.5 rounded-lg bg-travel-600 px-5 py-3 text-sm font-bold text-white shadow-md transition-colors hover:bg-travel-700 focus-visible:ring-2 focus-visible:ring-travel-600/40 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-travel-600"
        >
            {{ __('tourism.request.submit_offers_short') }}
            <x-travel-icon name="arrow_forward" class="h-4 w-4" />
        </button>
    </div>
</div>
