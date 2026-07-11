@php
    $dropdowns = [
        'banks' => [
            'label' => __('nav.banks.label'),
            'items' => [
                ['label' => __('nav.rates'), 'href' => route('rates.index')],
                ['label' => __('nav.banks.browse_all'), 'href' => route('organizations.index')],
                ['label' => __('nav.compare'), 'href' => route('organizations.compare')],
            ],
        ],
        'insurance' => [
            'label' => __('nav.insurance.label'),
            'items' => [
                ['label' => __('nav.insurance.items.auto'), 'href' => route('insurance.auto.request')],
                ['label' => __('nav.insurance.items.life'), 'href' => '#', 'soon' => true],
                ['label' => __('nav.insurance.items.medical'), 'href' => '#', 'soon' => true],
            ],
        ],
    ];

    $currentRoute = Route::current() ? Route::currentRouteName() : 'home';
    $currentRouteParams = Route::current() ? Route::current()->parameters() : [];

    // WhatsApp's real group link isn't set up yet - shown now with a
    // placeholder so the entry is visible, swapped in once WHATSAPP_GROUP_URL
    // is set (unlike Telegram, which only appears once it has a real URL).
    $joinLinks = collect([
        ['label' => 'Telegram', 'url' => config('services.telegram.group_url'), 'icon' => asset('images/telegram-logo.svg')],
        [
            'label' => 'Rates Bot',
            'url' => config('services.telegram.bot_username') ? 'https://t.me/' . config('services.telegram.bot_username') : null,
            'icon' => asset('images/telegram-logo.svg'),
        ],
        ['label' => 'WhatsApp', 'url' => config('services.whatsapp.group_url') ?: '#', 'icon' => asset('images/whatsapp-logo.svg')],
    ])->filter(fn ($link) => $link['url']);
@endphp

<header x-data="{ mobileOpen: false }" class="border-b border-placeholder">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-6 py-5 lg:px-10">
        <a href="{{ route('home') }}" class="shrink-0 font-logo text-2xl text-primary">
            Findex
        </a>

        {{-- "Home" is deliberately omitted here - the logo already links there,
             and every label saved keeps this row from wrapping in Armenian/Russian.
             Rates lives inside the Banks dropdown rather than as its own item. --}}
        <nav class="hidden flex-wrap items-center gap-x-5 gap-y-2 text-sm text-ink lg:flex">
            @foreach ($dropdowns as $key => $dropdown)
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex items-center gap-1 whitespace-nowrap hover:text-primary"
                        :aria-expanded="open"
                    >
                        {{ $dropdown['label'] }}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 fill-none stroke-current" :class="{ 'rotate-180': open }">
                            <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-transition
                        x-cloak
                        class="absolute left-0 top-full z-20 mt-3 w-72 rounded-2xl border border-placeholder bg-white p-2 shadow-lg ring-1 ring-placeholder/60"
                    >
                        @foreach ($dropdown['items'] as $item)
                            @if ($item['divider'] ?? false)
                                <hr class="-mx-2 my-2 border-placeholder">
                            @elseif ($item['soon'] ?? false)
                                {{-- whitespace-nowrap + shrink-0 keep the badge from squeezing the
                                     label onto two lines in longer languages (Armenian, Russian). --}}
                                <span class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm text-subtle" aria-disabled="true">
                                    <span class="whitespace-nowrap">{{ $item['label'] }}</span>
                                    <span class="shrink-0 rounded-full bg-placeholder/60 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-subtle uppercase">{{ __('nav.soon_badge') }}</span>
                                </span>
                            @else
                                <a href="{{ $item['href'] }}" class="block rounded-lg px-3 py-2.5 text-sm whitespace-nowrap text-body-text transition hover:bg-primary/5 hover:text-primary">
                                    {{ $item['label'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach

            <a href="{{ route('tourism.request') }}" class="whitespace-nowrap hover:text-primary">{{ __('tourism.nav_label') }}</a>
            <a href="{{ route('about') }}" class="whitespace-nowrap hover:text-primary">{{ __('nav.about') }}</a>
        </nav>

        <div class="flex items-center gap-5">
            <button type="button" aria-label="{{ __('common.search') }}" class="hidden text-ink hover:text-primary sm:block">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current">
                    <circle cx="11" cy="11" r="7" stroke-width="1.6" />
                    <path d="M20 20 16 16" stroke-width="1.6" stroke-linecap="round" />
                </svg>
            </button>

            @if ($joinLinks->isNotEmpty())
                <div x-data="{ open: false }" class="relative hidden sm:block" @click.outside="open = false">
                    <button
                        type="button"
                        @click="open = !open"
                        aria-label="{{ __('nav.get_updates') }}"
                        class="flex items-center gap-1 whitespace-nowrap rounded-full bg-primary px-3 py-1.5 text-sm text-white hover:bg-primary-dark"
                        :aria-expanded="open"
                    >
                        {{-- Collapses to an icon below xl - "Get Updates" (and its
                             Armenian/Russian equivalents) is one of the widest
                             elements in the header and the first to give up room. --}}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 fill-none stroke-current xl:hidden">
                            <path d="M15 17h5l-1.4-1.4a2 2 0 0 1-.6-1.4V11a6 6 0 0 0-5-5.9V4a1 1 0 1 0-2 0v1.1A6 6 0 0 0 6 11v3.2a2 2 0 0 1-.6 1.4L4 17h5" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M9 17a3 3 0 0 0 6 0" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span class="hidden xl:inline">{{ __('nav.get_updates') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 fill-none stroke-current" :class="{ 'rotate-180': open }">
                            <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        x-transition
                        x-cloak
                        class="absolute right-0 top-full z-20 mt-3 w-48 rounded-md border border-placeholder bg-white py-2 shadow-lg"
                    >
                        @foreach ($joinLinks as $link)
                            <a
                                href="{{ $link['url'] }}"
                                target="_blank"
                                rel="noopener"
                                class="flex items-center gap-2 px-4 py-2 text-sm text-body-text hover:bg-primary/5 hover:text-primary"
                            >
                                <img src="{{ $link['icon'] }}" alt="" class="h-4 w-4">
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Language switcher --}}
            <div x-data="{ open: false }" class="relative hidden sm:block" @click.outside="open = false">
                <button
                    type="button"
                    @click="open = !open"
                    class="flex items-center gap-1 text-sm text-ink hover:text-primary"
                    :aria-expanded="open"
                    aria-label="Language"
                >
                    {{ strtoupper(app()->getLocale()) }}
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 fill-none stroke-current" :class="{ 'rotate-180': open }">
                        <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>

                <div
                    x-show="open"
                    x-transition
                    x-cloak
                    class="absolute right-0 top-full z-20 mt-3 w-40 rounded-md border border-placeholder bg-white py-2 shadow-lg"
                >
                    @foreach (config('localization.available') as $code => $locale)
                        <a
                            href="{{ route($currentRoute, array_merge($currentRouteParams, ['locale' => $code])) }}"
                            class="block px-4 py-2 text-sm hover:bg-primary/5 hover:text-primary {{ $code === app()->getLocale() ? 'text-primary font-medium' : 'text-body-text' }}"
                        >
                            {{ $locale['native'] }}
                        </a>
                    @endforeach
                </div>
            </div>

            @auth
                <div x-data="{ open: false }" class="relative hidden sm:block" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="flex items-center gap-1 text-sm text-ink hover:text-primary" :aria-expanded="open">
                        {{ auth()->user()->name }}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 fill-none stroke-current" :class="{ 'rotate-180': open }">
                            <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>

                    <div x-show="open" x-transition x-cloak class="absolute right-0 top-full z-20 mt-3 w-48 rounded-md border border-placeholder bg-white py-2 shadow-lg">
                        <a href="{{ route('tourism.mine') }}" class="block px-4 py-2 text-sm text-body-text hover:bg-primary/5 hover:text-primary">
                            {{ __('tourism.mine.nav_label') }}
                        </a>
                        <a href="{{ route('alerts.index') }}" class="block px-4 py-2 text-sm text-body-text hover:bg-primary/5 hover:text-primary">
                            {{ __('alerts.heading') }}
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-body-text hover:bg-primary/5 hover:text-primary">
                                {{ __('common.logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('login') }}" class="hidden text-sm text-ink hover:text-primary sm:block">{{ __('common.login') }}</a>

                <a
                    href="{{ route('register') }}"
                    class="hidden border border-ink px-6 py-3 text-sm text-ink transition hover:bg-ink hover:text-white sm:block"
                >
                    {{ __('common.register') }}
                </a>
            @endauth

            <button
                type="button"
                @click="mobileOpen = !mobileOpen"
                class="text-ink lg:hidden"
                aria-label="{{ __('common.menu') }}"
                :aria-expanded="mobileOpen"
            >
                <svg x-show="!mobileOpen" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current">
                    <path d="M4 6h16M4 12h16M4 18h16" stroke-width="1.6" stroke-linecap="round" />
                </svg>
                <svg x-show="mobileOpen" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current">
                    <path d="M6 6l12 12M18 6 6 18" stroke-width="1.6" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    </div>

    <div x-show="mobileOpen" x-cloak x-transition class="border-t border-placeholder px-6 py-4 lg:hidden">
        <nav class="flex flex-col gap-1 text-sm text-ink">
            <a href="{{ route('home') }}" class="rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary">{{ __('nav.home') }}</a>

            @foreach ($dropdowns as $dropdown)
                <div x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary">
                        {{ $dropdown['label'] }}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 fill-none stroke-current" :class="{ 'rotate-180': open }">
                            <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <div x-show="open" x-cloak class="ml-4 flex flex-col gap-1 border-l border-placeholder pl-4">
                        @foreach ($dropdown['items'] as $item)
                            @if ($item['divider'] ?? false)
                                <hr class="my-2 border-placeholder">
                            @elseif ($item['soon'] ?? false)
                                <span class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-subtle" aria-disabled="true">
                                    <span class="whitespace-nowrap">{{ $item['label'] }}</span>
                                    <span class="shrink-0 rounded-full bg-placeholder/60 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-subtle uppercase">{{ __('nav.soon_badge') }}</span>
                                </span>
                            @else
                                <a href="{{ $item['href'] }}" class="rounded-lg px-3 py-2.5 whitespace-nowrap text-body-text hover:bg-primary/5 hover:text-primary">
                                    {{ $item['label'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach

            <a href="{{ route('tourism.request') }}" class="rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary">{{ __('tourism.nav_label') }}</a>
            <a href="{{ route('about') }}" class="rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary">{{ __('nav.about') }}</a>

            @if ($joinLinks->isNotEmpty())
                <div class="mt-1 border-t border-placeholder pt-3">
                    <p class="px-2 pb-1 text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('nav.get_updates') }}</p>
                    @foreach ($joinLinks as $link)
                        <a
                            href="{{ $link['url'] }}"
                            target="_blank"
                            rel="noopener"
                            class="flex items-center gap-2 rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary"
                        >
                            <img src="{{ $link['icon'] }}" alt="" class="h-4 w-4">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-3 flex items-center gap-3 border-t border-placeholder pt-4">
                @foreach (config('localization.available') as $code => $locale)
                    <a
                        href="{{ route($currentRoute, array_merge($currentRouteParams, ['locale' => $code])) }}"
                        class="rounded-md px-2 py-1 text-sm {{ $code === app()->getLocale() ? 'text-primary font-medium' : 'text-body-text' }}"
                    >
                        {{ strtoupper($code) }}
                    </a>
                @endforeach
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-4 border-t border-placeholder pt-4">
                @auth
                    <span class="text-ink">{{ auth()->user()->name }}</span>
                    <a href="{{ route('tourism.mine') }}" class="text-ink hover:text-primary">{{ __('tourism.mine.nav_label') }}</a>
                    <a href="{{ route('alerts.index') }}" class="text-ink hover:text-primary">{{ __('alerts.heading') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="border border-ink px-5 py-2.5 text-ink hover:bg-ink hover:text-white">
                            {{ __('common.logout') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-ink hover:text-primary">{{ __('common.login') }}</a>
                    <a href="{{ route('register') }}" class="border border-ink px-5 py-2.5 text-ink hover:bg-ink hover:text-white">{{ __('common.register') }}</a>
                @endauth
            </div>
        </nav>
    </div>
</header>
