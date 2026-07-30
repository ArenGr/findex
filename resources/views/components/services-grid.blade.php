@php
    $services = [
        ['key' => 'currency_exchange', 'image' => 'images/services/currency-exchange.svg', 'href' => route('rates.index')],
        ['key' => 'credit_card', 'image' => 'images/services/credit-card.png', 'href' => route('offers')],
        ['key' => 'insurance', 'image' => 'images/services/insurance.png', 'href' => route('insurance.auto.request')],
        ['key' => 'travel', 'image' => 'images/services/travel.svg', 'href' => route('tourism.request')],
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
                <a href="{{ $service['href'] }}" class="group flex flex-col items-center justify-center gap-5 rounded-2xl border border-primary/20 bg-white px-6 py-10 text-center shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary hover:shadow-lg">
                    <span class="flex h-20 w-20 items-center justify-center rounded-2xl bg-primary/5 transition duration-300 group-hover:bg-primary/10">
                        <img src="{{ asset($service['image']) }}" alt="" class="h-14 w-14 object-contain">
                    </span>
                    <span class="font-semibold text-ink">{{ __('home.services.' . $service['key']) }}</span>
                </a>
            @endforeach
        </div>
    </div>
</section>
