@php
    // Each category gets a soft/pastel tint (icon backdrop) and a matching
    // border line - defined in resources/css/app.css so the hex values live
    // in one place. Cards stay white by default (not fully colored) - the
    // tinted icon backdrop is enough to read as distinct per category
    // without turning the section into a rainbow.
    $services = [
        ['key' => 'currency_exchange', 'image' => 'images/services/currency-exchange.png', 'href' => route('rates.index'), 'border' => 'border-currency-line', 'tint' => 'bg-currency-tint'],
        ['key' => 'credit_card', 'image' => 'images/services/credit-card.png', 'href' => route('offers'), 'border' => 'border-cards-line', 'tint' => 'bg-cards-tint'],
        ['key' => 'insurance', 'image' => 'images/services/insurance.png', 'href' => route('insurance.auto.request'), 'border' => 'border-insurance-line', 'tint' => 'bg-insurance-tint'],
        ['key' => 'travel', 'image' => 'images/services/travel.png', 'href' => route('tourism.request'), 'border' => 'border-travel-line', 'tint' => 'bg-travel-tint'],
    ];
@endphp

<section class="border-t border-placeholder bg-white">
    <div class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        <div class="mx-auto max-w-2xl text-center">
            <span class="inline-flex items-center rounded-full bg-primary/10 px-4 py-1.5 text-xs font-semibold tracking-wide text-primary uppercase">
                {{ __('home.services.eyebrow') }}
            </span>
            <h2 class="mt-4 font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('home.services.heading') }}</h2>
            <p class="mt-3 text-sm leading-relaxed text-muted">{{ __('home.services.subtitle') }}</p>
        </div>

        <div class="mt-12 grid grid-cols-2 gap-5 sm:grid-cols-4 lg:gap-6">
            @foreach ($services as $service)
                <a href="{{ $service['href'] }}" class="group relative flex flex-col items-center justify-center gap-5 rounded-2xl border {{ $service['border'] }} bg-white px-6 py-10 text-center shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                    <span class="flex h-32 w-44 items-center justify-center rounded-2xl {{ $service['tint'] }}">
                        <img src="{{ asset($service['image']) }}" alt="" class="h-25 w-40 object-contain transition duration-300 group-hover:scale-105">
                    </span>

                    <div>
                        <span class="font-semibold text-ink">{{ __('home.services.' . $service['key']) }}</span>
                        <p class="mt-1.5 text-xs leading-relaxed text-muted">{{ __('home.services.' . $service['key'] . '_description') }}</p>
                    </div>

                    <span class="absolute right-4 bottom-4 flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-primary bg-primary text-white transition-colors duration-300 group-hover:bg-white group-hover:text-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3 w-3 fill-none stroke-current">
                            <path d="M7 17 17 7M9 7h8v8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>
