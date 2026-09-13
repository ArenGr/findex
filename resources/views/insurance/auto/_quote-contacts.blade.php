{{-- How to reach this insurer, when they have told us. --}}
@if ($organization->has_contact_info)
    <div class="flex flex-wrap gap-2">
        @if ($organization->contact_phone)
            <a href="tel:{{ preg_replace('/[^\d+]/', '', $organization->contact_phone) }}" class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/40 text-ink transition hover:bg-placeholder/60" aria-label="{{ __('organizations.contact_call') }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </a>
        @endif
        @if ($organization->contact_whatsapp)
            <a href="https://wa.me/{{ preg_replace('/\D/', '', $organization->contact_whatsapp) }}" target="_blank" rel="noopener" class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/40 text-ink transition hover:bg-placeholder/60" aria-label="{{ __('organizations.contact_whatsapp') }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2zm5.6 14.2c-.2.6-1.2 1.2-1.7 1.2-.4.1-1 .1-1.6-.1-.4-.1-.9-.3-1.5-.5-2.6-1.1-4.3-3.8-4.4-4-.1-.2-1-1.4-1-2.6s.6-1.8.9-2c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2.1.3 0 .5l-.3.5c-.2.2-.3.4-.1.7.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.2.1.4.1.5-.1l.7-.9c.2-.2.4-.2.6-.1l1.8.9c.2.1.4.2.5.3.1.2.1.7-.1 1.3z"/></svg>
            </a>
        @endif
        @if ($organization->contact_telegram)
            <a href="https://t.me/{{ ltrim($organization->contact_telegram, '@') }}" target="_blank" rel="noopener" class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/40 text-ink transition hover:bg-placeholder/60" aria-label="{{ __('organizations.contact_telegram') }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.9 4.3 18.6 20c-.2 1-.9 1.3-1.7.8l-4.6-3.4-2.2 2.1c-.2.2-.5.5-.9.5l.3-4.6 8.4-7.6c.4-.3-.1-.5-.6-.2L7.2 13 2.7 11.6c-1-.3-1-.9.2-1.4l17-6.5c.8-.3 1.5.2 1.2 1z"/></svg>
            </a>
        @endif
        @if ($organization->contact_instagram)
            <a href="https://instagram.com/{{ ltrim($organization->contact_instagram, '@') }}" target="_blank" rel="noopener" class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/40 text-ink transition hover:bg-placeholder/60" aria-label="{{ __('organizations.contact_instagram') }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><line x1="17.5" y1="6.5" x2="17.5" y2="6.5"/></svg>
            </a>
        @endif
    </div>
@endif
