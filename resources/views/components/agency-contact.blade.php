@props(['response', 'organization' => null, 'fallback' => null])

@php
    // The details this agency gave when answering this particular request
    // take priority over whatever is on its public profile - that's the
    // number it asked to be reached on about this quote. The profile is a
    // fallback, not a replacement.
    $profile = $organization ?? $response->organization;

    $channels = array_filter([
        'phone' => $response->contact_phone ?: $profile?->contact_phone,
        'whatsapp' => $response->contact_whatsapp ?: $profile?->contact_whatsapp,
        'telegram' => $response->contact_telegram ?: $profile?->contact_telegram,
        'instagram' => $response->contact_instagram ?: $profile?->contact_instagram,
    ]);
@endphp

@if ($channels !== [])
    <div {{ $attributes->merge(['class' => 'flex flex-wrap gap-2']) }}>
        @isset ($channels['phone'])
            <a
                href="tel:{{ preg_replace('/[^\d+]/', '', $channels['phone']) }}"
                class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60"
            >
                📞 {{ __('tourism.results.contact_call') }}
            </a>
        @endisset

        @isset ($channels['whatsapp'])
            <a
                href="https://wa.me/{{ preg_replace('/\D/', '', $channels['whatsapp']) }}"
                target="_blank" rel="noopener"
                class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60"
            >
                💬 {{ __('tourism.results.contact_whatsapp') }}
            </a>
        @endisset

        @isset ($channels['telegram'])
            <a
                href="https://t.me/{{ ltrim($channels['telegram'], '@') }}"
                target="_blank" rel="noopener"
                class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60"
            >
                ✈️ {{ __('tourism.results.contact_telegram') }}
            </a>
        @endisset

        @isset ($channels['instagram'])
            <a
                href="https://instagram.com/{{ ltrim($channels['instagram'], '@') }}"
                target="_blank" rel="noopener"
                class="rounded-full bg-placeholder/40 px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/60"
            >
                📷 {{ __('tourism.results.contact_instagram') }}
            </a>
        @endisset
    </div>
@elseif ($fallback)
    <p {{ $attributes->merge(['class' => 'text-sm text-muted']) }}>{{ $fallback }}</p>
@endif
