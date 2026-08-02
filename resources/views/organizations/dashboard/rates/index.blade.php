@extends('layouts.dashboard')

@section('title', __('org.rates.title'))

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.rates.title') }}</h1>
        <a href="{{ route('org.dashboard.rates.create') }}" class="bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.rates.add') }}
        </a>
    </div>

    {{-- Only exchange offices take part in the currency exchange quote flow
    (see Organization::exchangePartnersForCurrency) - banks don't get this
    widget. --}}
    @if ($organization->type === 'exchange')
        <section class="mt-8 border border-placeholder p-5">
            <h2 class="font-heading text-base font-semibold text-ink">{{ __('org.rates.telegram_heading') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('org.rates.telegram_subheading') }}</p>

            @if ($organization->telegram_chat_id)
                <p class="mt-2 text-sm text-primary">{{ __('org.rates.telegram_connected') }}</p>

                <form method="POST" action="{{ route('org.dashboard.rates.refresh-connect-link') }}" class="mt-4" onsubmit="return confirm('{{ __('org.rates.telegram_connect_button') }}?')">
                    @csrf
                    <button type="submit" class="border border-placeholder px-4 py-2 text-sm font-medium text-ink hover:bg-placeholder/40">
                        {{ __('org.rates.telegram_connect_button') }}
                    </button>
                </form>
            @else
                <p class="mt-2 text-sm text-muted">{{ __('org.rates.telegram_not_connected') }}</p>
                <p class="mt-1 text-sm text-muted">{{ __('org.rates.telegram_hint') }}</p>

                <a
                    href="https://t.me/{{ $botUsername }}?start={{ $organization->telegram_connect_token }}"
                    target="_blank"
                    rel="noopener"
                    class="mt-4 inline-block bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
                >
                    {{ __('org.rates.telegram_connect_button') }}
                </a>
            @endif
        </section>
    @endif

    <div class="mt-6 divide-y divide-placeholder border-t border-placeholder">
        @forelse ($rates as $rate)
            <div class="flex items-center justify-between py-4 text-sm">
                <div>
                    <p class="font-medium text-ink">{{ $rate->currency->code }} · {{ __('organizations.rate_types.' . $rate->rate_type->value) }}</p>
                    <p class="text-xs text-muted">
                        {{ __('organizations.buy') }}: {{ $rate->buy_rate }} — {{ __('organizations.sell') }}: {{ $rate->sell_rate }}
                    </p>
                </div>
                <a href="{{ route('org.dashboard.rates.edit', $rate) }}" class="font-medium text-primary hover:underline">
                    {{ __('org.rates.edit') }}
                </a>
            </div>
        @empty
            <p class="py-6 text-sm text-muted">{{ __('org.rates.no_rates') }}</p>
        @endforelse
    </div>
@endsection
