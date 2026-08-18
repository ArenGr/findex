{{--
    The mobile counterpart to the sticky sidebar. Deliberately not the
    desktop panel squeezed into a phone: a seven-row summary pinned to a
    small screen would eat most of the viewport the form needs. Instead the
    trip condenses to one line, with the action beside it, and the full
    summary stays reachable as an ordinary card further up the page.

    Hidden from desktop entirely (md:hidden), where the real sidebar is
    already visible and a second submit button would be noise.
--}}
<div class="sticky bottom-0 -mx-4 mt-6 border-t border-border-subtle bg-white/95 px-4 py-3 backdrop-blur md:hidden">
    <div class="flex items-center gap-3">
        <div class="min-w-0 flex-1">
            <p class="truncate text-body-sm font-semibold text-on-surface" x-text="compactSummary"></p>
            <a href="#travel-request-summary" class="text-label-caps text-travel-primary hover:underline">
                {{ __('tourism.request.review_request') }}
            </a>
        </div>

        <button
            type="submit"
            class="flex shrink-0 items-center gap-1.5 rounded-lg bg-travel-primary px-5 py-3 text-label-caps font-bold text-white transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-travel-primary/40 focus-visible:outline-none"
        >
            {{ __('tourism.request.submit_offers_short') }}
            <x-travel-icon name="arrow_forward" class="h-4 w-4" />
        </button>
    </div>
</div>
