@props(['organization', 'includeFastResponder' => false])

@if ($organization->isTopRated() || ($includeFastResponder && $organization->isFastResponder()))
    <div class="flex flex-wrap items-center gap-2">
        @if ($organization->isTopRated())
            <span
                class="inline-flex items-center gap-1 rounded-full bg-accent-yellow/10 px-2.5 py-1 text-xs font-medium text-ink"
                title="{{ __('organizations.badge_top_rated_tooltip', ['rating' => \App\Models\Organization::TOP_RATED_MIN_RATING]) }}"
            >
                ⭐ {{ __('organizations.badge_top_rated') }}
            </span>
        @endif

        @if ($includeFastResponder && $organization->isFastResponder())
            <span
                class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                title="{{ __('organizations.badge_fast_responder_tooltip') }}"
            >
                ⚡ {{ __('organizations.badge_fast_responder') }}
            </span>
        @endif
    </div>
@endif
