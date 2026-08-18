@extends('layouts.dashboard')

@section('title', __('tourism.inbox.request_heading'))

@section('content')
    <a href="{{ route('org.dashboard.travel-requests.index') }}" class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-primary">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
            <path fill-rule="evenodd" d="M12.7 15.7a1 1 0 0 1-1.4 0l-5-5a1 1 0 0 1 0-1.4l5-5a1 1 0 1 1 1.4 1.4L8.4 10l4.3 4.3a1 1 0 0 1 0 1.4Z" clip-rule="evenodd" />
        </svg>
        {{ __('tourism.inbox.back_to_inbox') }}
    </a>

    <h1 class="mt-4 font-heading text-xl font-semibold text-ink">{{ __('tourism.inbox.request_heading') }}</h1>

    {{-- The trip requirements, and nothing beyond them. The traveler's name
         and email are deliberately not here: an agency needs to know what to
         price, not who is asking, until the traveler chooses to make contact. --}}
    <div class="mt-6 rounded-2xl border border-placeholder p-5">
        <x-trip-brief :request="$quoteRequest" />
    </div>

    @if (! $quoteRequest->is_open)
        <div class="mt-6 rounded-lg border border-placeholder bg-placeholder/20 px-4 py-3 text-sm text-ink">
            {{ __('tourism.inbox.request_closed') }}
        </div>
    @endif

    @if ($response->is_declined)
        <div class="mt-6 rounded-lg border border-placeholder bg-placeholder/20 px-4 py-3 text-sm text-ink">
            {{ __('tourism.inbox.declined_notice') }}
        </div>
    @elseif ($response->is_editable)
        @if ($response->has_replied)
            <div class="mt-6 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('tourism.inbox.editing_notice', ['time' => $response->responded_at->diffForHumans()]) }}
            </div>
        @endif

        <h2 class="mt-8 font-heading text-base font-semibold text-ink">
            {{ $response->has_replied ? __('tourism.inbox.edit_offer_heading') : __('tourism.inbox.send_offer_heading') }}
        </h2>

        <x-travel-offer-form
            class="mt-4"
            :action="route('org.dashboard.travel-requests.offer.store', $response)"
            :response="$response"
            :templates="$templates"
        />

        @unless ($response->has_replied)
            <form
                method="POST"
                action="{{ route('org.dashboard.travel-requests.decline', $response) }}"
                class="mt-6"
                onsubmit="return confirm('{{ __('tourism.inbox.decline_confirm') }}')"
            >
                @csrf
                <button type="submit" class="text-sm font-medium text-muted underline hover:text-ink">
                    {{ __('tourism.inbox.decline') }}
                </button>
            </form>
        @endunless
    @else
        {{-- Already answered, but the request has since closed or run out.
             The offer stays readable - it is the record of what was quoted -
             it just can't be changed any more. --}}
        <h2 class="mt-8 font-heading text-base font-semibold text-ink">{{ __('tourism.inbox.your_offer_heading') }}</h2>

        <div class="mt-4 space-y-4">
            @foreach ($response->suggestions as $suggestion)
                <div class="rounded-2xl border border-placeholder p-5">
                    <p class="font-heading text-lg font-bold text-primary">
                        {{ rtrim(rtrim((string) $suggestion->price_amount, '0'), '.') }} {{ $suggestion->price_currency }}
                    </p>
                    <x-offer-facts :offer="$suggestion" :request="$quoteRequest" class="mt-4" />
                </div>
            @endforeach
        </div>
    @endif
@endsection
