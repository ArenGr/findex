@extends('layouts.dashboard')

@section('title', __('org.rates.title'))

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.rates.title') }}</h1>
        <a href="{{ route('org.dashboard.rates.create') }}" class="bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.rates.add') }}
        </a>
    </div>

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
