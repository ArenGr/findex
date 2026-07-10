@props(['ad'])

<aside {{ $attributes->merge(['class' => 'w-full max-w-[300px] border border-placeholder bg-white']) }}>
    <div class="flex items-center justify-between border-b border-placeholder px-4 py-2">
        <span class="text-[10px] font-semibold uppercase tracking-wider text-subtle">{{ __('ads.sponsored_label') }}</span>
        <div class="flex items-center gap-2">
            <span class="text-[10px] uppercase tracking-wider text-subtle">{{ __('ads.ad_label') }}</span>
            <button
                type="button"
                @click="dismissed = true; sessionStorage.setItem('ad-dismissed-{{ $ad->id }}', '1')"
                aria-label="{{ __('ads.dismiss') }}"
                title="{{ __('ads.dismiss') }}"
                class="text-subtle hover:text-ink"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                    <path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </div>

    <a href="{{ $ad->href }}" class="block p-5 transition hover:bg-placeholder/10">
        <div class="flex h-32 items-center justify-center bg-accent-blue/10">
            @if ($ad->logo_url)
                <img src="{{ $ad->logo_url }}" alt="{{ $ad->advertiser }}" class="h-14 w-14 rounded-full object-contain">
            @else
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-accent-blue font-heading text-lg font-bold text-white">
                    {{ $ad->initials }}
                </div>
            @endif
        </div>

        <p class="mt-4 text-xs font-medium text-muted">{{ $ad->advertiser }}</p>
        <p class="mt-1 font-heading text-lg font-bold leading-snug text-ink">{{ $ad->headline }}</p>
        <p class="mt-2 text-sm text-muted">{{ $ad->body }}</p>

        <span class="mt-4 inline-block bg-primary px-4 py-2 text-xs font-medium text-white">
            {{ $ad->cta_label }}
        </span>
    </a>
</aside>
