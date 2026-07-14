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

    <section class="mt-8 border border-placeholder p-5">
        <h2 class="font-heading text-base font-semibold text-ink">{{ __('tourism.dashboard.lead_preferences_heading') }}</h2>
        <p class="mt-1 text-sm text-muted">{{ __('tourism.dashboard.lead_preferences_subheading') }}</p>

        <form method="POST" action="{{ route('org.dashboard.tourism.lead-preferences.update') }}" class="mt-4 grid grid-cols-2 gap-4" novalidate>
            @csrf
            @method('PUT')

            <x-form-input
                type="number" step="1000" min="0"
                name="min_lead_budget_amd"
                :label="__('tourism.dashboard.min_lead_budget')"
                :value="$organization->min_lead_budget_amd"
            />
            <x-form-input
                type="number" min="1" max="20"
                name="min_lead_party_size"
                :label="__('tourism.dashboard.min_lead_party_size')"
                :value="$organization->min_lead_party_size"
            />

            <button type="submit" class="col-span-2 mt-1 bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark sm:w-auto sm:justify-self-start">
                {{ __('tourism.dashboard.lead_preferences_save') }}
            </button>
        </form>
    </section>

    @if ($benchmark->isNotEmpty())
        <section class="mt-8 border border-placeholder p-5">
            <h2 class="font-heading text-base font-semibold text-ink">{{ __('tourism.dashboard.benchmark_heading') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('tourism.dashboard.benchmark_subheading') }}</p>

            <div class="mt-4 divide-y divide-placeholder border-t border-placeholder">
                @foreach ($benchmark as $row)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                        <span class="font-medium text-ink">{{ __('destinations.' . $row['country_code']) }}</span>
                        <div class="text-right">
                            <p class="text-ink">{{ __('tourism.dashboard.benchmark_own_avg') }}: {{ number_format($row['own_avg']) }} {{ __('tourism.request.amd') }}</p>
                            @if ($row['market_avg'] !== null)
                                @php
                                    $diffPercent = $row['market_avg'] > 0 ? round((($row['own_avg'] - $row['market_avg']) / $row['market_avg']) * 100) : 0;
                                @endphp
                                <p class="text-xs {{ $diffPercent > 0 ? 'text-red-600' : ($diffPercent < 0 ? 'text-primary' : 'text-subtle') }}">
                                    {{ __('tourism.dashboard.benchmark_market_avg') }}: {{ number_format($row['market_avg']) }} {{ __('tourism.request.amd') }}
                                    @if ($diffPercent > 0)
                                        ({{ __('tourism.dashboard.benchmark_above_market', ['percent' => abs($diffPercent)]) }})
                                    @elseif ($diffPercent < 0)
                                        ({{ __('tourism.dashboard.benchmark_below_market', ['percent' => abs($diffPercent)]) }})
                                    @else
                                        ({{ __('tourism.dashboard.benchmark_at_market') }})
                                    @endif
                                </p>
                            @else
                                <p class="text-xs text-subtle">{{ __('tourism.dashboard.benchmark_no_market_data') }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($servedDestinations->isNotEmpty())
        <section class="mt-8 border border-placeholder p-5">
            <h2 class="font-heading text-base font-semibold text-ink">{{ __('tourism.dashboard.pause_heading') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('tourism.dashboard.pause_subheading') }}</p>

            <div class="mt-4 divide-y divide-placeholder border-t border-placeholder">
                @foreach ($servedDestinations as $destination)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="text-sm">
                            <span class="font-medium text-ink">{{ __('destinations.' . $destination->country_code) }}</span>
                            @if ($destination->isActive())
                                <span class="ml-2 text-xs text-primary">{{ __('tourism.dashboard.pause_status_active') }}</span>
                            @elseif ($destination->paused_until)
                                <span class="ml-2 text-xs text-subtle">{{ __('tourism.dashboard.pause_status_until', ['date' => $destination->paused_until->translatedFormat('d M Y')]) }}</span>
                            @else
                                <span class="ml-2 text-xs text-subtle">{{ __('tourism.dashboard.pause_status_paused') }}</span>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('org.dashboard.tourism.destinations.pause', $destination) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="is_paused" value="{{ $destination->isActive() ? '1' : '0' }}">
                            @if ($destination->isActive())
                                <input
                                    type="date"
                                    name="paused_until"
                                    min="{{ now()->addDay()->toDateString() }}"
                                    class="rounded-md border border-border-muted px-2 py-1 text-xs text-ink focus:border-primary focus:outline-none"
                                    title="{{ __('tourism.dashboard.pause_until_placeholder') }}"
                                >
                                <button type="submit" class="border border-placeholder px-3 py-1.5 text-xs font-medium text-ink hover:bg-placeholder/40">
                                    {{ __('tourism.dashboard.pause_button') }}
                                </button>
                            @else
                                <button type="submit" class="border border-placeholder px-3 py-1.5 text-xs font-medium text-primary hover:bg-placeholder/40">
                                    {{ __('tourism.dashboard.resume_button') }}
                                </button>
                            @endif
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <h2 class="mt-10 font-heading text-lg font-semibold text-ink">{{ __('tourism.dashboard.requests_heading') }}</h2>
    <p class="mt-1 text-sm text-muted">{{ __('tourism.dashboard.requests_hint') }}</p>

    <div class="mt-4 divide-y divide-placeholder border-t border-placeholder">
        @forelse ($quoteResponses as $response)
            @php $request = $response->quoteRequest; @endphp
            <div class="py-4 text-sm">
                <div class="flex items-center justify-between gap-4">
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
                        <span class="shrink-0 text-primary">{{ __('tourism.results.replied_label', ['time' => $response->responded_at->diffForHumans()]) }}</span>
                    @elseif ($response->is_declined)
                        <span class="shrink-0 text-subtle">{{ __('tourism.dashboard.declined_label') }}</span>
                    @else
                        <span class="shrink-0 text-subtle">{{ __('tourism.results.waiting_label') }}</span>
                    @endif
                </div>

                @if ($request->hotel_name)
                    <p class="mt-1 text-xs text-muted">{{ $request->hotel_name }}</p>
                @endif

                @if ($response->has_replied)
                    @if ($response->reply_text)
                        <p class="mt-2 border border-placeholder bg-placeholder/20 px-3 py-2 text-xs text-ink">{{ $response->reply_text }}</p>
                    @endif

                    @foreach ($response->suggestions as $suggestion)
                        <div class="mt-2 {{ !$loop->first ? 'border-t border-placeholder pt-2' : '' }}">
                            @if ($response->suggestions->count() > 1)
                                <p class="text-xs font-semibold text-subtle">{{ str_replace(':number', $loop->iteration, __('tourism.respond.suggestion_label')) }}</p>
                            @endif
                            <p class="font-heading text-lg font-bold text-primary">
                                {{ rtrim(rtrim((string) $suggestion->price_amount, '0'), '.') }} {{ $suggestion->price_currency }}
                            </p>
                            @if ($suggestion->offered_hotel_name)
                                <p class="text-xs text-ink"><span class="text-subtle">{{ __('tourism.dashboard.hotel_label') }}:</span> {{ $suggestion->offered_hotel_name }}</p>
                            @endif
                            @if ($suggestion->flight_details)
                                <p class="text-xs text-ink"><span class="text-subtle">{{ __('tourism.dashboard.flight_label') }}:</span> {{ $suggestion->flight_details }}</p>
                            @endif
                            @if ($suggestion->inclusions)
                                <p class="text-xs text-ink"><span class="text-subtle">{{ __('tourism.dashboard.inclusions_label') }}:</span> {{ $suggestion->inclusions }}</p>
                            @endif
                            @if ($suggestion->promo_code)
                                <p class="text-xs text-ink">
                                    <span class="text-subtle">{{ __('tourism.dashboard.promo_label') }}:</span>
                                    {{ $suggestion->promo_code }}
                                    @if ($suggestion->promo_note)
                                        — {{ $suggestion->promo_note }}
                                    @endif
                                </p>
                                @if ($suggestion->is_claimed)
                                    <p class="text-xs text-primary">
                                        {{ __('tourism.dashboard.promo_claimed_by', [
                                            'name' => $suggestion->claimedBy->name,
                                            'email' => $suggestion->claimedBy->email,
                                            'time' => $suggestion->claimed_at->diffForHumans(),
                                        ]) }}
                                    </p>
                                @else
                                    <p class="text-xs text-subtle">{{ __('tourism.dashboard.promo_not_claimed_yet') }}</p>
                                @endif
                            @endif
                        </div>
                    @endforeach
                @elseif (!$response->is_declined)
                    <a href="{{ $response->secureRespondUrl() }}" target="_blank" rel="noopener" class="mt-2 inline-block text-xs font-medium text-primary hover:underline">
                        {{ __('tourism.dashboard.open_response_link') }} &rarr;
                    </a>
                @endif
            </div>
        @empty
            <p class="py-6 text-sm text-muted">{{ __('tourism.dashboard.no_requests') }}</p>
        @endforelse
    </div>
@endsection
