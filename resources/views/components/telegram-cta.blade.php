@php
    $groupUrl = config('services.telegram.group_url');
@endphp

@if ($groupUrl)
    <section class="border-t border-placeholder bg-primary/5">
        <div class="mx-auto flex max-w-7xl flex-col items-center gap-6 px-6 py-12 text-center lg:px-10">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-6 w-6 fill-primary">
                    <path d="M21.05 3.64 2.98 10.6c-1.24.49-1.23 1.17-.23 1.47l4.63 1.45 1.79 5.5c.22.6.11.84.74.84.49 0 .7-.22.97-.48l2.33-2.26 4.84 3.57c.89.49 1.53.24 1.76-.83l3.18-15c.32-1.3-.49-1.89-1.94-1.22Zm-11.86 11.1-1.42-4.36 9.7-6.02c.46-.27.88-.13.54.17l-8.82 10.21Z"/>
                </svg>
            </span>

            <div>
                <h2 class="font-heading text-xl font-semibold text-ink">{{ __('telegram.heading') }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-muted">{{ __('telegram.subtitle') }}</p>
            </div>

            <a
                href="{{ $groupUrl }}"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 bg-primary px-6 py-3 text-sm text-white hover:bg-primary-dark"
            >
                {{ __('telegram.button') }}
            </a>
        </div>
    </section>
@endif
