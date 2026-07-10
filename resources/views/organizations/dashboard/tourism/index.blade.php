@extends('layouts.dashboard')

@section('title', __('tourism.nav_label'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('tourism.nav_label') }}</h1>

    <section class="mt-6 border border-placeholder p-5">
        <h2 class="font-heading text-base font-semibold text-ink">{{ __('tourism.dashboard.telegram_heading') }}</h2>

        @if ($organization->telegram_chat_id)
            <p class="mt-2 text-sm text-primary">{{ __('tourism.dashboard.telegram_connected') }}</p>

            <form method="POST" action="{{ route('org.dashboard.tourism.refresh-connect-link') }}" class="mt-4" onsubmit="return confirm('{{ __('tourism.dashboard.telegram_connect_button') }}?')">
                @csrf
                <button type="submit" class="border border-placeholder px-4 py-2 text-sm font-medium text-ink hover:bg-placeholder/40">
                    {{ __('tourism.dashboard.telegram_connect_button') }}
                </button>
            </form>
        @else
            <p class="mt-2 text-sm text-muted">{{ __('tourism.dashboard.telegram_not_connected') }}</p>
            <p class="mt-1 text-sm text-muted">{{ __('tourism.dashboard.telegram_hint') }}</p>

            <a
                href="https://t.me/{{ $botUsername }}?start={{ $organization->telegram_connect_token }}"
                target="_blank"
                rel="noopener"
                class="mt-4 inline-block bg-primary px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
            >
                {{ __('tourism.dashboard.telegram_connect_button') }}
            </a>
        @endif
    </section>

    <section class="mt-8 border border-placeholder p-5">
        <h2 class="font-heading text-base font-semibold text-ink">{{ __('tourism.dashboard.destinations_heading') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('tourism.dashboard.destinations_subheading') }}</p>

        <form method="POST" action="{{ route('org.dashboard.tourism.destinations.update') }}" class="mt-4">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($destinations as $code)
                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input
                            type="checkbox"
                            name="destinations[]"
                            value="{{ $code }}"
                            @checked(in_array($code, $servedCountryCodes, true))
                            class="rounded border-border-muted text-primary focus:ring-primary"
                        >
                        {{ __('destinations.' . $code) }}
                    </label>
                @endforeach
            </div>

            <button type="submit" class="mt-5 bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
                {{ __('tourism.dashboard.destinations_save') }}
            </button>
        </form>
    </section>

    <h2 class="mt-10 font-heading text-lg font-semibold text-ink">{{ __('tourism.dashboard.requests_heading') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('tourism.dashboard.requests_hint') }}</p>

    <div class="mt-4 divide-y divide-placeholder border-t border-placeholder">
        @forelse ($quoteResponses as $response)
            @php $request = $response->quoteRequest; @endphp
            <div class="py-4 text-sm">
                <div class="flex items-center justify-between">
                    <span class="font-medium text-ink">
                        {{ __('tourism.results.trip_summary', [
                            'destination' => __('destinations.' . $request->destination_country),
                            'check_in' => $request->check_in->translatedFormat('d M'),
                            'check_out' => $request->check_out->translatedFormat('d M Y'),
                            'adults' => $request->adults,
                            'children' => $request->children,
                        ]) }}
                    </span>
                    @if ($response->has_replied)
                        <span class="text-primary">{{ __('tourism.results.replied_label', ['time' => $response->responded_at->diffForHumans()]) }}</span>
                    @else
                        <span class="text-subtle">{{ __('tourism.results.waiting_label') }}</span>
                    @endif
                </div>
                @if ($request->hotel_name)
                    <p class="mt-1 text-xs text-muted">{{ $request->hotel_name }}</p>
                @endif
                @if ($response->reply_text)
                    <p class="mt-2 border border-placeholder bg-placeholder/20 px-3 py-2 text-xs text-ink">{{ $response->reply_text }}</p>
                @endif
            </div>
        @empty
            <p class="py-6 text-sm text-muted">{{ __('tourism.dashboard.no_requests') }}</p>
        @endforelse
    </div>
@endsection
