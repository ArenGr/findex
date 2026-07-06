@php
    $bankLinks = \App\Models\Organization::active()
        ->where('type', 'bank')
        ->orderBy('name')
        ->get()
        ->map(fn ($org) => ['label' => $org->name, 'href' => route('organizations.show', $org)])
        ->all();

    $dropdowns = [
        'banks' => [
            'label' => __('nav.banks.label'),
            'items' => $bankLinks,
        ],
        'insurance' => [
            'label' => __('nav.insurance.label'),
            'items' => [
                ['label' => __('nav.insurance.items.auto'), 'href' => '#'],
                ['label' => __('nav.insurance.items.life'), 'href' => '#'],
                ['label' => __('nav.insurance.items.medical'), 'href' => '#'],
            ],
        ],
    ];

    $currentRoute = Route::current() ? Route::currentRouteName() : 'home';
    $currentRouteParams = Route::current() ? Route::current()->parameters() : [];
@endphp

<header x-data="{ mobileOpen: false }" class="border-b border-placeholder">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-8 px-6 py-5 lg:px-10">
        <a href="{{ route('home') }}" class="shrink-0 font-logo text-2xl text-primary">
            Findex
        </a>

        <nav class="hidden items-center gap-8 text-sm text-ink lg:flex">
            <a href="{{ route('home') }}" class="hover:text-primary">{{ __('nav.home') }}</a>

            @foreach ($dropdowns as $key => $dropdown)
                <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex items-center gap-1 hover:text-primary"
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
                        class="absolute left-0 top-full z-20 mt-3 w-56 rounded-md border border-placeholder bg-white py-2 shadow-lg"
                    >
                        @foreach ($dropdown['items'] as $item)
                            <a href="{{ $item['href'] }}" class="block px-4 py-2 text-sm text-body-text hover:bg-primary/5 hover:text-primary">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <a href="#" class="hover:text-primary">{{ __('nav.brokers') }}</a>
            <a href="{{ route('about') }}" class="hover:text-primary">{{ __('nav.about') }}</a>
        </nav>

        <div class="flex items-center gap-5">
            <button type="button" aria-label="{{ __('common.search') }}" class="hidden text-ink hover:text-primary sm:block">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current">
                    <circle cx="11" cy="11" r="7" stroke-width="1.6" />
                    <path d="M20 20 16 16" stroke-width="1.6" stroke-linecap="round" />
                </svg>
            </button>

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

                    <div x-show="open" x-transition x-cloak class="absolute right-0 top-full z-20 mt-3 w-40 rounded-md border border-placeholder bg-white py-2 shadow-lg">
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
                            <a href="{{ $item['href'] }}" class="rounded-md px-2 py-2 text-body-text hover:bg-primary/5 hover:text-primary">
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <a href="#" class="rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary">{{ __('nav.brokers') }}</a>
            <a href="{{ route('about') }}" class="rounded-md px-2 py-2 hover:bg-primary/5 hover:text-primary">{{ __('nav.about') }}</a>

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

            <div class="mt-2 flex items-center gap-4 border-t border-placeholder pt-4">
                @auth
                    <span class="text-ink">{{ auth()->user()->name }}</span>
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
