@extends('layouts.app')

@section('title', __('tourism.respond.title') . ' — Findex')

@php
    $flags = [
        'AE' => '🇦🇪', 'EG' => '🇪🇬', 'GE' => '🇬🇪', 'GR' => '🇬🇷',
        'TH' => '🇹🇭', 'CY' => '🇨🇾', 'IT' => '🇮🇹', 'FR' => '🇫🇷', 'ES' => '🇪🇸',
    ];
@endphp

@section('content')
    <section class="mx-auto max-w-2xl px-6 py-16 lg:px-10">
        @if (!$response)
            <div class="rounded-2xl border border-dashed border-placeholder p-8 text-center">
                <h1 class="font-heading text-xl font-semibold text-ink">{{ __('tourism.respond.not_found_heading') }}</h1>
                <p class="mt-2 text-sm text-muted">{{ __('tourism.respond.not_found_body') }}</p>
            </div>
        @else
            @php $request = $response->quoteRequest; @endphp

            @if ($response->has_replied)
                <div class="rounded-2xl border border-primary/30 bg-primary/5 p-6 text-center">
                    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('tourism.respond.success_heading') }}</h1>
                    <p class="mt-2 text-sm text-body-text">{{ __('tourism.respond.success_body') }}</p>
                </div>

                @if ($response->reply_text)
                    <div class="mt-8 rounded-2xl border border-placeholder p-6">
                        <p class="text-sm text-ink">{{ $response->reply_text }}</p>
                    </div>
                @endif

                @if ($response->has_contact_info)
                    <div class="mt-4 rounded-2xl border border-placeholder p-6">
                        <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('tourism.respond.contact_heading') }}</p>
                        <dl class="mt-3 space-y-1 text-sm text-ink">
                            @if ($response->contact_phone)
                                <div><dt class="inline text-subtle">{{ __('tourism.respond.contact_phone_label') }}:</dt> <dd class="inline">{{ $response->contact_phone }}</dd></div>
                            @endif
                            @if ($response->contact_whatsapp)
                                <div><dt class="inline text-subtle">{{ __('tourism.respond.contact_whatsapp_label') }}:</dt> <dd class="inline">{{ $response->contact_whatsapp }}</dd></div>
                            @endif
                            @if ($response->contact_telegram)
                                <div><dt class="inline text-subtle">{{ __('tourism.respond.contact_telegram_label') }}:</dt> <dd class="inline">{{ $response->contact_telegram }}</dd></div>
                            @endif
                            @if ($response->contact_instagram)
                                <div><dt class="inline text-subtle">{{ __('tourism.respond.contact_instagram_label') }}:</dt> <dd class="inline">{{ $response->contact_instagram }}</dd></div>
                            @endif
                        </dl>
                    </div>
                @endif

                @foreach ($response->suggestions as $suggestion)
                    <div class="mt-4 rounded-2xl border border-placeholder p-6">
                        <h2 class="font-heading text-base font-semibold text-ink">
                            {{ __('tourism.respond.your_offer_heading') }}
                            @if ($response->suggestions->count() > 1)
                                <span class="text-sm font-normal text-subtle">({{ $loop->iteration }}/{{ $response->suggestions->count() }})</span>
                            @endif
                        </h2>

                        <p class="mt-3 font-heading text-2xl font-bold text-primary">
                            {{ rtrim(rtrim((string) $suggestion->price_amount, '0'), '.') }} {{ $suggestion->price_currency }}
                        </p>

                        <dl class="mt-3 space-y-1 text-sm text-ink">
                            @if ($suggestion->offered_hotel_name)
                                <div><dt class="inline text-subtle">{{ __('tourism.respond.hotel_label') }}:</dt> <dd class="inline">{{ $suggestion->offered_hotel_name }}</dd></div>
                            @endif
                            @if ($suggestion->flight_details)
                                <div><dt class="inline text-subtle">{{ __('tourism.respond.flight_label') }}:</dt> <dd class="inline">{{ $suggestion->flight_details }}</dd></div>
                            @endif
                            @if ($suggestion->inclusions)
                                <div><dt class="inline text-subtle">{{ __('tourism.respond.inclusions_label') }}:</dt> <dd class="inline">{{ $suggestion->inclusions }}</dd></div>
                            @endif
                        </dl>

                        @if ($suggestion->attachment_path)
                            <a href="{{ Storage::url($suggestion->attachment_path) }}" target="_blank" rel="noopener" class="mt-3 inline-block text-sm font-medium text-primary hover:underline">
                                {{ __('tourism.results.attachment_label') }} &darr;
                            </a>
                        @endif

                        @if ($suggestion->promo_code)
                            <div class="mt-3 rounded-xl border border-dashed border-primary/40 bg-primary/5 px-4 py-3 text-sm">
                                <p class="font-semibold text-ink">{{ __('tourism.respond.promo_code_label') }}: {{ $suggestion->promo_code }}</p>
                                @if ($suggestion->promo_note)
                                    <p class="mt-1 text-xs text-ink">{{ $suggestion->promo_note }}</p>
                                @endif
                                @if ($suggestion->is_claimed)
                                    <p class="mt-1 text-xs text-primary">
                                        {{ __('tourism.respond.promo_claimed_by', [
                                            'name' => $suggestion->claimedBy->name,
                                            'email' => $suggestion->claimedBy->email,
                                            'time' => $suggestion->claimed_at->diffForHumans(),
                                        ]) }}
                                    </p>
                                @else
                                    <p class="mt-1 text-xs text-subtle">{{ __('tourism.respond.promo_not_claimed_yet') }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            @elseif ($response->is_declined)
                <div class="rounded-2xl border border-placeholder p-8 text-center">
                    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('tourism.respond.declined_heading') }}</h1>
                    <p class="mt-2 text-sm text-muted">{{ __('tourism.respond.declined_body') }}</p>
                </div>
            @elseif (!$request->is_open)
                <div class="rounded-2xl border border-placeholder p-8 text-center">
                    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('tourism.respond.expired_heading') }}</h1>
                    <p class="mt-2 text-sm text-muted">{{ __('tourism.respond.expired_body') }}</p>
                </div>
            @else
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ __('tourism.respond.heading') }}</h1>

                {{-- Customer's request --}}
                <div class="mt-6 rounded-2xl border border-placeholder p-5 shadow-sm">
                    <p class="text-xs font-semibold tracking-wide text-subtle uppercase">{{ __('tourism.respond.customer_request_heading') }}</p>

                    <div class="mt-3 flex items-center gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/5 text-2xl">
                            {{ $flags[$request->destination_country] ?? '✈️' }}
                        </span>
                        <div class="min-w-0">
                            <p class="font-heading font-semibold text-ink">
                                {{ __('tourism.results.trip_summary', [
                                    'destination' => __('destinations.' . $request->destination_country),
                                    'check_in' => $request->check_in->translatedFormat('d M'),
                                    'check_out' => $request->check_out->translatedFormat('d M Y'),
                                    'adults' => $request->adults,
                                    'children' => $request->children,
                                ]) }}
                            </p>
                            @if ($request->hotel_name)
                                <p class="text-sm text-muted">{{ $request->hotel_name }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($request->all_inclusive || $request->insurance)
                        <div class="mt-4 flex flex-wrap gap-2 text-xs">
                            @if ($request->all_inclusive)
                                <span class="rounded-full bg-placeholder/40 px-3 py-1 text-ink">{{ __('tourism.request.all_inclusive') }}</span>
                            @endif
                            @if ($request->insurance)
                                <span class="rounded-full bg-placeholder/40 px-3 py-1 text-ink">{{ __('tourism.request.insurance') }}</span>
                            @endif
                        </div>
                    @endif

                    @if ($request->budget_min_amd || $request->budget_max_amd)
                        <p class="mt-4 text-sm text-ink">
                            <span class="text-subtle">{{ __('tourism.respond.budget_label') }}:</span>
                            @if ($request->budget_min_amd && $request->budget_max_amd)
                                {{ number_format($request->budget_min_amd) }}–{{ number_format($request->budget_max_amd) }} {{ __('tourism.request.amd') }}
                            @elseif ($request->budget_min_amd)
                                {{ __('tourism.respond.budget_at_least', ['amount' => number_format($request->budget_min_amd)]) }}
                            @else
                                {{ __('tourism.respond.budget_up_to', ['amount' => number_format($request->budget_max_amd)]) }}
                            @endif
                        </p>
                    @endif

                    @if ($request->notes)
                        <p class="mt-4 rounded-xl bg-placeholder/20 px-4 py-3 text-sm text-ink">{{ $request->notes }}</p>
                    @endif
                </div>

                {{-- The same offer form the agency's dashboard inbox uses
                     (see the travel-offer-form component) - one form, so a
                     quote sent from a Telegram link and one sent from the
                     dashboard can't drift apart in what they ask for. --}}
                <x-travel-offer-form
                    class="mt-8"
                    :action="route('tourism.respond.store', ['locale' => app()->getLocale(), 'token' => $response->response_token])"
                    :response="$response"
                    :templates="$templates"
                />
            @endif
        @endif
    </section>
@endsection
