@extends('layouts.dashboard')

@section('title', __('org.rates.edit'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.rates.edit') }}</h1>

    <p class="mt-2 text-sm text-muted">
        {{ $rate->currency->code }} · {{ __('organizations.rate_types.' . $rate->rate_type->value) }}
    </p>

    <form method="POST" action="{{ route('org.dashboard.rates.update', $rate) }}" class="mt-6 max-w-xl space-y-5" novalidate>
        @csrf
        @method('PUT')

        <x-form-input name="buy_rate" type="number" step="0.0001" min="0" :label="__('org.rates.buy')" :value="$rate->buy_rate" required />
        <x-form-input name="sell_rate" type="number" step="0.0001" min="0" :label="__('org.rates.sell')" :value="$rate->sell_rate" required />

        <button type="submit" class="bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.rates.save') }}
        </button>
    </form>
@endsection
