@props([
    'show' => 'loading',   // Alpine expression that gates the overlay
    'title' => null,
    'subtitle' => null,
    'count' => null,       // number of insurers being asked, for the checklist
])

{{--
    Full-screen "getting your quotes" loader, from the approved Stitch loading
    screen. Rendered with Findex tokens and inline icons (no CDN Tailwind,
    Google fonts or Material Symbols).

    It covers the whole viewport on a solid light ground so it reads as a page
    rather than a translucent overlay - the request is one synchronous POST,
    so this is what the user watches while the server fans out to the insurers
    and comes back with the results page.

    The three-line checklist and its pulse ring are decoration: there is no
    real per-step progress to report from a single blocking request, only the
    reassurance that something is happening. Animations live in app.css
    (findex-stagger-*, findex-pulse-ring).

    Driven by an Alpine boolean in the parent scope (default `loading`); the
    parent flips it true on submit. x-cloak keeps it hidden before Alpine
    boots so it never flashes on first paint.
--}}
<div
    x-show="{{ $show }}"
    x-cloak
    class="fixed inset-0 z-[60] flex flex-col items-center justify-center overflow-hidden bg-surface-alt px-6"
    role="status"
    aria-live="polite"
>
    {{-- Ambient dot pattern --}}
    <div class="pointer-events-none absolute inset-0 opacity-25" style="background-image: radial-gradient(circle at center, var(--color-primary) 1px, transparent 1px); background-size: 40px 40px;"></div>

    <div class="relative z-10 flex w-full max-w-md flex-col items-center text-center">
        <h1 class="findex-stagger-1 mb-8 font-heading text-2xl font-bold text-ink">
            {{ $title ?? __('common.loading.title') }}
        </h1>

        {{-- Status card --}}
        <div class="relative mb-8 w-full overflow-hidden rounded-3xl border border-placeholder bg-white p-5 shadow-sm">
            {{-- Indeterminate progress bar along the top --}}
            <div class="absolute left-0 top-0 h-1 w-full bg-placeholder">
                <div class="h-full w-2/3 bg-primary"></div>
            </div>

            <div class="mt-2 flex flex-col gap-4 text-left">
                {{-- Done --}}
                <div class="findex-stagger-1 flex items-center gap-3">
                    <svg class="h-5 w-5 shrink-0 text-primary" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1.2 14.5-3.5-3.5 1.4-1.4 2.1 2.1 4.6-4.6 1.4 1.4-6 6z"/></svg>
                    <span class="text-base text-ink">{{ __('common.loading.step_sent') }}</span>
                </div>

                {{-- Done --}}
                <div class="findex-stagger-2 flex items-center gap-3">
                    <svg class="h-5 w-5 shrink-0 text-primary" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm-1.2 14.5-3.5-3.5 1.4-1.4 2.1 2.1 4.6-4.6 1.4 1.4-6 6z"/></svg>
                    <span class="text-base text-ink">{{ __('common.loading.step_checking', ['count' => $count ?? __('common.loading.some')]) }}</span>
                </div>

                {{-- Active --}}
                <div class="findex-stagger-3 -mx-3 flex items-center gap-3 rounded-lg bg-primary/10 p-3">
                    <span class="findex-pulse-ring relative flex h-6 w-6 items-center justify-center text-primary">
                        <span class="h-2.5 w-2.5 rounded-full bg-primary"></span>
                    </span>
                    <span class="text-sm font-medium text-primary">{{ __('common.loading.step_collecting') }}</span>
                </div>
            </div>
        </div>

        <p class="findex-stagger-3 max-w-xs text-sm text-muted">
            {{ $subtitle ?? __('common.loading.subtitle') }}
        </p>
    </div>
</div>
