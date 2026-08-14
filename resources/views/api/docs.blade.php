@extends('layouts.app')

@section('title', __('api.title') . ' — Findex')
@section('description', __('api.tagline'))

@section('content')
    <section class="mx-auto max-w-5xl px-6 py-16 lg:px-10">
        <h1 class="font-heading text-3xl leading-tight font-bold break-words text-ink">{{ __('api.title') }}</h1>
        <p class="mt-3 max-w-2xl text-base leading-relaxed break-words text-muted">{{ __('api.tagline') }}</p>

        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('api.keys.index') }}" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold break-words text-white transition hover:bg-primary-dark">
                {{ __('api.get_key') }}
            </a>
        </div>

        <h2 class="mt-12 font-heading text-xl font-semibold break-words text-ink">{{ __('api.endpoints') }}</h2>

        <div class="mt-4 overflow-x-auto rounded-xl border border-placeholder">
            <table class="w-full border-collapse text-sm">
                <tbody>
                    @foreach ($endpoints as $endpoint)
                        <tr class="border-b border-placeholder last:border-b-0">
                            <td class="px-5 py-3 align-top">
                                <code class="text-xs break-all text-ink">GET {{ $endpoint['path'] }}</code>
                            </td>
                            <td class="px-5 py-3 align-top text-sm break-words text-muted">{{ $endpoint['summary'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h2 class="mt-12 font-heading text-xl font-semibold break-words text-ink">{{ __('api.auth_heading') }}</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed break-words text-muted">{{ __('api.auth_body') }}</p>

        <pre class="mt-4 overflow-x-auto rounded-xl border border-placeholder bg-placeholder/20 px-5 py-4 text-xs text-ink"><code>curl -H "Authorization: Bearer fx_your_key" \
  {{ url('/api/v1/rates/best?currency=USD') }}</code></pre>

        <h2 class="mt-12 font-heading text-xl font-semibold break-words text-ink">{{ __('api.plans') }}</h2>

        {{-- Read straight from config/api.php, so this page cannot disagree
        with what the limiter actually enforces. --}}
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($plans as $slug => $plan)
                <div class="flex min-w-0 flex-col rounded-xl border border-placeholder bg-white p-5">
                    <span class="text-xs font-semibold tracking-wider text-muted uppercase">{{ $plan['label'] }}</span>

                    <p class="mt-2 font-heading text-2xl font-bold break-words text-ink">
                        @if ($plan['price_usd_monthly'] === null)
                            &mdash;
                        @elseif ($plan['price_usd_monthly'] === 0)
                            {{ __('api.free') }}
                        @else
                            ${{ $plan['price_usd_monthly'] }}
                            <span class="text-xs font-normal text-muted">/ {{ __('api.per_month') }}</span>
                        @endif
                    </p>

                    <dl class="mt-3 space-y-1 text-sm text-muted">
                        <div class="break-words">
                            {{ $plan['requests_per_day'] === null ? __('api.unmetered') : number_format($plan['requests_per_day']).' '.__('api.per_day') }}
                        </div>
                        <div class="break-words">
                            {{ $plan['requests_per_minute'] === null ? __('api.unmetered') : number_format($plan['requests_per_minute']).' '.__('api.per_minute') }}
                        </div>
                    </dl>
                </div>
            @endforeach
        </div>

        <p class="mt-4 text-sm break-words text-muted">
            {{ __('api.without_key') }}:
            {{ number_format($anonymous['requests_per_day']) }} {{ __('api.per_day') }}.
        </p>
    </section>
@endsection
