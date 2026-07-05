@php
    $columns = [
        [
            'title' => __('footer.columns.company.title'),
            'links' => [
                ['label' => __('footer.columns.company.links.about'), 'href' => '#'],
                ['label' => __('footer.columns.company.links.team'), 'href' => '#'],
                ['label' => __('footer.columns.company.links.careers'), 'href' => '#'],
                ['label' => __('footer.columns.company.links.news'), 'href' => '#'],
            ],
        ],
        [
            'title' => __('footer.columns.help.title'),
            'links' => [
                ['label' => __('footer.columns.help.links.help_center'), 'href' => '#'],
                ['label' => __('footer.columns.help.links.faq'), 'href' => '#'],
                ['label' => __('footer.columns.help.links.contact'), 'href' => '#'],
            ],
        ],
        [
            'title' => __('footer.columns.legal.title'),
            'links' => [
                ['label' => __('footer.columns.legal.links.terms'), 'href' => '#'],
                ['label' => __('footer.columns.legal.links.privacy'), 'href' => '#'],
                ['label' => __('footer.columns.legal.links.cookies'), 'href' => '#'],
            ],
        ],
    ];

    $socials = ['X', 'YouTube', 'Instagram', 'TikTok'];
@endphp

<footer class="border-t border-placeholder">
    <div class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="grid grid-cols-1 gap-12 lg:grid-cols-4">
            <div>
                <a href="{{ route('home') }}" class="font-logo text-2xl text-primary">Findex</a>

                <p class="mt-6 text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('footer.download_app') }}</p>
                <a
                    href="#"
                    class="mt-3 inline-flex items-center gap-2 rounded-md border border-ink px-4 py-2 text-sm text-ink hover:bg-ink hover:text-white"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-current">
                        <path d="M16.365 1.43c0 1.14-.462 2.06-1.386 2.85-.924.79-1.877 1.24-2.86 1.16-.087-1.09.29-2.03 1.13-2.82.83-.79 1.87-1.19 3.116-1.19zM20.5 17.06c-.5 1.14-.94 1.98-1.63 2.98-.96 1.39-1.9 2.11-2.83 2.13-.72.02-1.19-.19-2.02-.5-.83-.31-1.47-.5-2.53-.5-1.1 0-1.75.19-2.55.5-.83.32-1.28.53-2 .52-.9-.02-1.83-.75-2.79-2.15-1.5-2.19-2.4-4.85-2.42-7.35-.02-1.99.62-3.63 1.9-4.9 1.02-1.02 2.28-1.55 3.68-1.57 1.02-.02 1.98.5 2.83.5.85 0 1.98-.63 3.34-.55.57.02 2.18.23 3.22 1.72-.08.06-1.92 1.13-1.9 3.36.02 2.68 2.35 3.57 2.38 3.58-.02.06-.36 1.24-1.21 2.43z"/>
                    </svg>
                    {{ __('footer.app_store') }}
                </a>
            </div>

            @foreach ($columns as $column)
                <div>
                    <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ $column['title'] }}</p>
                    <ul class="mt-4 space-y-3 text-sm text-body-text">
                        @foreach ($column['links'] as $link)
                            <li><a href="{{ $link['href'] }}" class="hover:text-primary">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="mt-16 border-t border-placeholder pt-8">
            <p class="max-w-3xl text-xs leading-relaxed text-subtle">
                {{ __('footer.disclaimer') }}
            </p>

            <div class="mt-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <p class="text-xs text-subtle">{{ __('footer.copyright', ['year' => now()->year]) }}</p>

                <div class="flex items-center gap-4 text-subtle">
                    @foreach ($socials as $social)
                        <a href="#" aria-label="{{ $social }}" class="hover:text-primary">
                            <span class="text-xs font-medium">{{ $social }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</footer>
