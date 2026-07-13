@extends('layouts.dashboard')

@section('title', __('org.rates.add'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.rates.add') }}</h1>

    <form method="POST" action="{{ route('org.dashboard.rates.store') }}" class="mt-6 max-w-xl space-y-5" novalidate>
        @csrf

        <div>
            <label for="currency_id" class="block text-sm font-medium text-ink">{{ __('org.rates.currency') }}</label>
            <select
                name="currency_id"
                id="currency_id"
                required
                class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('currency_id') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
            >
                <option value="" disabled selected>{{ __('org.rates.currency') }}</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->id }}" @selected(old('currency_id') == $currency->id)>{{ $currency->code }} — {{ $currency->name }}</option>
                @endforeach
            </select>
            @error('currency_id')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="rate_type" class="block text-sm font-medium text-ink">{{ __('org.rates.rate_type') }}</label>
            <select
                name="rate_type"
                id="rate_type"
                required
                class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('rate_type') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
            >
                <option value="" disabled selected>{{ __('org.rates.rate_type') }}</option>
                @foreach ($rateTypes as $rateType)
                    <option value="{{ $rateType->value }}" @selected(old('rate_type') === $rateType->value)>{{ __('organizations.rate_types.' . $rateType->value) }}</option>
                @endforeach
            </select>
            @error('rate_type')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <x-form-input name="buy_rate" type="number" step="0.0001" min="0" :label="__('org.rates.buy')" required />
        <x-form-input name="sell_rate" type="number" step="0.0001" min="0" :label="__('org.rates.sell')" required />

        <button type="submit" class="bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.rates.save') }}
        </button>
    </form>
@endsection
