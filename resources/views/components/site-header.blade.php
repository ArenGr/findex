@php
    $currentRoute = Route::current() ? Route::currentRouteName() : 'home';
    $currentRouteParams = Route::current() ? Route::current()->parameters() : [];

    // A nav entry is "active" if the current route name starts with any of
    // its prefixes - lets one entry own a whole family of routes (e.g.
    // exchange.request/.show/.mine/.respond) without listing every route
    // name individually.
    $isActive = fn (array $prefixes) => collect($prefixes)->contains(
        fn ($prefix) => str_starts_with($currentRoute, $prefix)
    );

    // Bank products, filtered to what an admin has switched on (Feature
    // Toggles in the panel -> FeatureToggle). A disabled category is absent
    // here and 404s its own page, so the menu never links somewhere the
    // visitor can't go - which is also why there's no "coming soon" state
    // in the nav any more.
    $enabledCategories = \App\Http\Controllers\OfferController::enabledCategories();

    $product = fn (string $slug) => [
        'label' => __('offers.categories.'.$slug.'.title'),
        'href' => route('banks.show', $slug),
    ];

    // Null when every product in the section is switched off, so the
    // heading doesn't survive its own contents.
    $section = function (string $headingKey, array $slugs) use ($enabledCategories, $product) {
        $slugs = array_values(array_intersect($slugs, $enabledCategories));

        return $slugs === [] ? null : ['heading' => __($headingKey), 'items' => array_map($product, $slugs)];
    };

    $bankingColumns = array_values(array_filter([
        array_values(array_filter([
            $section('nav.banking.groups.loans', ['mortgages', 'personal-loans', 'business-loans', 'student-loans']),
        ])),
        array_values(array_filter([
            $section('nav.banking.groups.cards', ['credit-cards']),
            $section('nav.banking.groups.saving', ['banking', 'investing']),
        ])),
    ], fn (array $column) => $column !== []));

    // Insurance/Travel/About are plain links (below), not dropdowns - each
    // has exactly one real destination today, and a dropdown with a single
    // item is a click with nothing behind it. Rates, Compare Banks and the
    // bank directory stay reachable by URL, just not from this menu.
    //
    // One panel, columns of headed sections - not a nested flyout. With a
    // handful of products a hover-to-open submenu is two interactions to
    // reach any leaf, and it put a second floating panel at a different
    // height overlapping the first, which just read as broken. Everything
    // is visible in one click here, and there's no alignment to get wrong.
    // Links and the Banking menu share one ordered list so the sequence is
    // decided here rather than falling out of which loop runs first - which
    // is what previously forced every plain link after the dropdown.
    // array_filter drops the Banking entry when every product is toggled
    // off, and the rest of the nav closes up around the gap.
    //
    // Exchange rates leads: it's the most-used page and isn't behind a
    // feature toggle, so it stays put even with Banking gone entirely.
    $navItems = array_values(array_filter([
        ['label' => __('nav.rates'), 'icon' => 'rates', 'href' => route('rates.index'), 'active' => $isActive(['rates.'])],

        $bankingColumns === [] ? null : [
            'label' => __('nav.banking.label'),
            'icon' => 'banking',
            'active' => $isActive(['banks.']),
            'columns' => $bankingColumns,
        ],

        ['label' => __('nav.insurance.label'), 'icon' => 'insurance', 'href' => route('insurance.auto.request'), 'active' => $isActive(['insurance.'])],
        ['label' => __('nav.travel.label'), 'icon' => 'travel', 'href' => route('tourism.request'), 'active' => $isActive(['tourism.'])],
        ['label' => __('nav.about'), 'icon' => 'about', 'href' => route('about'), 'active' => $currentRoute === 'about'],
    ]));

    // Telegram is the only channel we actually run, and connecting it is an
    // account action, not a group invite: the link goes to the alert page's
    // connect flow (auth-gated, so a guest registers first), which binds the
    // chat to the user via users.telegram_connect_token. The bare t.me link it
    // replaced started a bot session tied to nothing.
    //
    // WhatsApp used to sit beside it rendering href="#" - a dead link in the
    // header of every page, because WHATSAPP_GROUP_URL has never been set.
    $connectLinks = collect([
        [
            'label' => 'Telegram',
            'url' => route('alerts.index', ['channel' => 'telegram']),
            'icon' => asset('images/telegram-logo.svg'),
            'connected' => auth()->check() && auth()->user()->telegram_chat_id,
        ],
    ]);
@endphp

<header x-data="{ mobileOpen: false }" class="border-b border-placeholder">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-6 py-5 lg:px-10">
        <a href="{{ route('home') }}" class="shrink-0 font-logo text-2xl text-primary">
            Findex
        </a>

        {{-- "Home" is deliberately omitted here - the logo already links
             there, and every label saved keeps this row from wrapping in
             Armenian/Russian. --}}
        <nav class="hidden flex-wrap items-center gap-x-5 gap-y-2 text-sm text-ink lg:flex">
            @foreach ($navItems as $navItem)
                @if (empty($navItem['columns']))
                    <a href="{{ $navItem['href'] }}" class="flex items-center gap-1.5 whitespace-nowrap hover:text-primary {{ $navItem['active'] ? 'text-primary' : '' }}">
                        <x-nav-icon :name="$navItem['icon']" />
                        {{ $navItem['label'] }}
                    </a>
                @else
                @php($dropdown = $navItem)
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex items-center gap-1.5 whitespace-nowrap hover:text-primary {{ $dropdown['active'] ? 'text-primary' : '' }}"
                        :aria-expanded="open"
                    >
                        <x-nav-icon :name="$dropdown['icon']" />
                        {{ $dropdown['label'] }}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 fill-none stroke-current" :class="{ 'rotate-180': open }">
                            <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <div
                        x-show="open"
                        x-transition
                        x-cloak
                        class="absolute left-0 top-full z-20 mt-3 rounded-2xl border border-placeholder bg-white p-5 shadow-lg ring-1 ring-placeholder/60 {{ count($dropdown['columns']) > 1 ? 'w-[34rem]' : 'w-64' }}"
                    >
                        <div class="grid gap-x-8 {{ count($dropdown['columns']) > 1 ? 'grid-cols-2' : 'grid-cols-1' }}">
                            @foreach ($dropdown['columns'] as $column)
                                <div class="space-y-5">
                                    @foreach ($column as $section)
                                        <div>
                                            <p class="px-2 pb-1 text-[10px] font-semibold tracking-wider text-subtle uppercase">
                                                {{ $section['heading'] }}
                                            </p>
                                            @foreach ($section['items'] as $item)
                                                <a href="{{ $item['href'] }}" class="flex items-start justify-between gap-3 rounded-lg px-2 py-2 text-sm leading-snug text-body-text transition hover:bg-primary/5 hover:text-primary">
                                                    {{ $item['label'] }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            @endforeach
        </nav>

        <div class="flex items-center gap-5">
            @if ($connectLinks->isNotEmpty())
                <div x-data="{ open: false }" class="relative hidden sm:block" @click.outside="open = false">
                    <button
                        type="button"
                        @click="open = !open"
                        aria-label="{{ __('nav.connect') }}"
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
                        <span class="hidden xl:inline">{{ __('nav.connect') }}</span>
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
                        @foreach ($connectLinks as $link)
                            <a
                                href="{{ $link['url'] }}"
                                class="flex items-center gap-2 px-4 py-2 text-sm text-body-text hover:bg-primary/5 hover:text-primary"
                            >
                                <img src="{{ $link['icon'] }}" alt="" class="h-4 w-4 shrink-0">
                                <span class="min-w-0 flex-1 break-words">{{ $link['label'] }}</span>
                                @if ($link['connected'])
                                    <span class="shrink-0 text-xs font-medium text-primary">{{ __('nav.connected') }}</span>
                                @endif
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
                    class="flex items-center gap-1.5 text-sm text-ink hover:text-primary"
                    :aria-expanded="open"
                    aria-label="Language"
                >
                    {{-- Flag only (no text) - was the raw locale code
                    ("HY"/"EN"/"RU"), which a visitor who doesn't read
                    Armenian can't recognize as a language switcher at all.
                    The native label is kept for screen readers (sr-only)
                    even though it's not shown visually. --}}
                    <span class="text-base leading-none">{{ config('localization.available')[app()->getLocale()]['flag'] ?? '🌐' }}</span>
                    <span class="sr-only">{{ config('localization.available')[app()->getLocale()]['native'] ?? strtoupper(app()->getLocale()) }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 fill-none stroke-current" :class="{ 'rotate-180': open }">
                        <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>

                <div
                    x-show="open"
                    x-transition
                    x-cloak
                    class="absolute right-0 top-full z-20 mt-3 w-14 rounded-md border border-placeholder bg-white py-2 shadow-lg"
                >
                    @foreach (config('localization.available') as $code => $locale)
                        <a
                            href="{{ route($currentRoute, array_merge($currentRouteParams, ['locale' => $code])) }}"
                            class="flex items-center justify-center px-4 py-2 text-base hover:bg-primary/5 {{ $code === app()->getLocale() ? 'ring-1 ring-inset ring-primary/30' : '' }}"
                        >
                            {{ $locale['flag'] }}
                            <span class="sr-only">{{ $locale['native'] }}</span>
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

    {{-- A full-screen overlay rather than an inline expanding panel - the
    panel's content (nav + dropdowns + language + auth links) is taller than
    many phone viewports, so an inline panel left page content peeking in
    underneath it once scrolled. overflow-y-auto lets the overlay itself
    scroll instead. It duplicates the Findex wordmark + a close button here
    rather than relying on the header's own toggle button staying visible,
    since the header isn't sticky - if the menu is opened after scrolling
    down the page, the header (and its button) would already be scrolled
    out of view while this fixed overlay stays put. --}}
    <div
        x-show="mobileOpen"
        x-cloak
        x-transition
        @keydown.escape.window="mobileOpen = false"
        role="dialog"
        aria-modal="true"
        class="fixed inset-0 z-50 overflow-y-auto bg-white lg:hidden"
    >
        <div class="flex items-center justify-between border-b border-placeholder px-6 py-5">
            <a href="{{ route('home') }}" class="shrink-0 font-logo text-2xl text-primary" @click="mobileOpen = false">
                Findex
            </a>
            <button type="button" @click="mobileOpen = false" class="text-ink" aria-label="{{ __('common.menu') }}">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-6 w-6 fill-none stroke-current">
                    <path d="M6 6l12 12M18 6 6 18" stroke-width="1.6" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="px-6 py-4">
        <nav class="flex flex-col gap-1 text-sm text-ink">
            <a href="{{ route('home') }}" class="rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary">{{ __('nav.home') }}</a>

            @foreach ($navItems as $navItem)
                @if (empty($navItem['columns']))
                    <a href="{{ $navItem['href'] }}" class="flex items-center gap-2 rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary {{ $navItem['active'] ? 'text-primary' : '' }}">
                        <x-nav-icon :name="$navItem['icon']" />
                        {{ $navItem['label'] }}
                    </a>
                @else
                @php($dropdown = $navItem)
                <div x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary {{ $dropdown['active'] ? 'text-primary' : '' }}">
                        <span class="flex items-center gap-2">
                            <x-nav-icon :name="$dropdown['icon']" />
                            {{ $dropdown['label'] }}
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="h-2 w-3 fill-none stroke-current" :class="{ 'rotate-180': open }">
                            <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <div x-show="open" x-cloak class="ml-4 flex flex-col gap-4 border-l border-placeholder pl-4">
                        {{-- Sections stacked flat, not a nested accordion: on a
                        phone the extra tap buys nothing, and every product is
                        already one screen away. --}}
                        @foreach ($dropdown['columns'] as $column)
                            @foreach ($column as $section)
                                <div class="flex flex-col gap-1">
                                    <p class="px-3 text-[10px] font-semibold tracking-wider text-subtle uppercase">
                                        {{ $section['heading'] }}
                                    </p>
                                    @foreach ($section['items'] as $item)
                                        <a href="{{ $item['href'] }}" class="flex items-start justify-between gap-3 rounded-lg px-3 py-2.5 leading-snug text-body-text hover:bg-primary/5 hover:text-primary">
                                            {{ $item['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach

            @if ($connectLinks->isNotEmpty())
                <div class="mt-1 border-t border-placeholder pt-3">
                    <p class="px-2 pb-1 text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('nav.connect') }}</p>
                    @foreach ($connectLinks as $link)
                        <a
                            href="{{ $link['url'] }}"
                            class="flex items-center gap-2 rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary"
                        >
                            <img src="{{ $link['icon'] }}" alt="" class="h-4 w-4 shrink-0">
                            <span class="min-w-0 flex-1 break-words">{{ $link['label'] }}</span>
                            @if ($link['connected'])
                                <span class="shrink-0 text-xs font-medium text-primary">{{ __('nav.connected') }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-3 flex items-center gap-2 border-t border-placeholder pt-4">
                @foreach (config('localization.available') as $code => $locale)
                    <a
                        href="{{ route($currentRoute, array_merge($currentRouteParams, ['locale' => $code])) }}"
                        class="flex h-9 w-9 items-center justify-center rounded-md text-lg {{ $code === app()->getLocale() ? 'ring-1 ring-inset ring-primary/30' : '' }}"
                    >
                        {{ $locale['flag'] }}
                        <span class="sr-only">{{ $locale['native'] }}</span>
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
    </div>
</header>
