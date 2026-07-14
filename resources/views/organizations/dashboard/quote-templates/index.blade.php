@extends('layouts.dashboard')

@section('title', __('org.quote_templates.title'))

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.quote_templates.title') }}</h1>
        <a href="{{ route('org.dashboard.quote-templates.create') }}" class="bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary-dark">
            {{ __('org.quote_templates.add') }}
        </a>
    </div>
    <p class="mt-1 text-sm text-muted">{{ __('org.quote_templates.subtitle') }}</p>

    <div class="mt-6 divide-y divide-placeholder border-t border-placeholder">
        @forelse ($templates as $template)
            <div class="flex items-center justify-between py-4 text-sm">
                <div>
                    <p class="font-medium text-ink">
                        {{ $template->name }}
                        @if ($template->destination_country)
                            <span class="ml-2 text-xs text-subtle">({{ __('destinations.' . $template->destination_country) }})</span>
                        @else
                            <span class="ml-2 text-xs text-subtle">({{ __('org.quote_templates.any_destination') }})</span>
                        @endif
                    </p>
                    @if ($template->price_amount)
                        <p class="text-xs text-muted">{{ rtrim(rtrim((string) $template->price_amount, '0'), '.') }} {{ $template->price_currency }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('org.dashboard.quote-templates.edit', $template) }}" class="font-medium text-primary hover:underline">
                        {{ __('org.quote_templates.edit') }}
                    </a>
                    <form method="POST" action="{{ route('org.dashboard.quote-templates.destroy', $template) }}" onsubmit="return confirm('{{ __('org.quote_templates.delete') }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-medium text-red-600 hover:underline">{{ __('org.quote_templates.delete') }}</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="py-6 text-sm text-muted">{{ __('org.quote_templates.no_templates') }}</p>
        @endforelse
    </div>
@endsection
