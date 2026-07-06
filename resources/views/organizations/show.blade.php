@extends('layouts.app')

@section('title', $organization->name . ' — Findex')

@section('content')
    <section class="mx-auto max-w-4xl px-6 py-16 lg:px-10">

        @if (session('status') === 'review-submitted')
            <div class="mb-8 border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('organizations.review_submitted') }}
            </div>
        @endif

        {{-- Header --}}
        <div class="flex flex-col items-start gap-6 sm:flex-row sm:items-center">
            @if ($organization->logo)
                <img src="{{ $organization->logo }}" alt="{{ $organization->name }}" class="h-16 w-16 rounded-full object-contain">
            @else
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-primary font-heading text-xl font-bold text-white">
                    {{ Str::of($organization->name)->substr(0, 2)->upper() }}
                </div>
            @endif

            <div>
                <h1 class="font-heading text-2xl font-bold text-ink lg:text-3xl">{{ $organization->name }}</h1>
                <div class="mt-2 flex items-center gap-3">
                    <x-star-rating :rating="$averageRating ?? 0" size="h-5 w-5" />
                    <span class="text-sm text-muted">
                        {{ $averageRating ? number_format($averageRating, 1) : '—' }}
                        ({{ trans_choice('organizations.reviews_count', $reviewsCount, ['count' => $reviewsCount]) }})
                    </span>
                </div>
            </div>
        </div>

        {{-- Facts --}}
        <dl class="mt-8 grid grid-cols-1 gap-4 border-y border-placeholder py-6 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('organizations.website') }}</dt>
                <dd class="mt-1 text-sm text-ink">
                    <a href="{{ $organization->website }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">
                        {{ $organization->website }}
                    </a>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('organizations.type') }}</dt>
                <dd class="mt-1 text-sm text-ink">{{ __('organizations.types.' . $organization->type) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('organizations.country') }}</dt>
                <dd class="mt-1 text-sm text-ink">{{ __('organizations.countries.' . $organization->country_code) }}</dd>
            </div>
        </dl>

        @if ($organization->description)
            <p class="mt-6 text-sm leading-relaxed text-body-text">{{ $organization->description }}</p>
        @endif

        {{-- Reviews --}}
        <h2 class="mt-12 font-heading text-xl font-semibold text-ink">{{ __('organizations.reviews_heading') }}</h2>

        @auth
            <form
                method="POST"
                action="{{ route('reviews.store', $organization) }}"
                class="mt-6 border border-placeholder p-6"
                x-data="{ rating: {{ old('rating', $myReview->rating ?? 0) }} }"
            >
                @csrf

                <label class="block text-sm font-medium text-ink">{{ __('organizations.your_rating') }}</label>
                <input type="hidden" name="rating" :value="rating">
                <div class="mt-2 flex items-center gap-1">
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="button" @click="rating = {{ $i }}" class="focus:outline-none" aria-label="{{ $i }}">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="h-8 w-8" :class="rating >= {{ $i }} ? 'fill-accent-yellow' : 'fill-placeholder'">
                                <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L10 14.9l-5.2 2.61.99-5.79-4.21-4.1 5.82-.85z" />
                            </svg>
                        </button>
                    @endfor
                </div>
                @error('rating')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror

                @if ($organization->branches->isNotEmpty())
                    <label for="branch_id" class="mt-5 block text-sm font-medium text-ink">{{ __('organizations.branch') }}</label>
                    <select
                        name="branch_id"
                        id="branch_id"
                        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                    >
                        <option value="">{{ __('organizations.no_branch') }}</option>
                        @foreach ($organization->branches as $branch)
                            <option value="{{ $branch->id }}" @selected(old('branch_id', $myReview->branch_id ?? null) == $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <label for="comment" class="mt-5 block text-sm font-medium text-ink">{{ __('organizations.your_comment') }}</label>
                <textarea
                    name="comment"
                    id="comment"
                    rows="4"
                    class="mt-1.5 block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('comment') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                >{{ old('comment', $myReview->comment ?? '') }}</textarea>
                @error('comment')
                    <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="mt-5 bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark">
                    {{ $myReview ? __('organizations.update_review') : __('organizations.submit_review') }}
                </button>
            </form>
        @else
            <p class="mt-6 border border-placeholder p-6 text-sm text-muted">
                {{ __('organizations.login_to_review') }}
                <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">{{ __('common.login') }}</a>
                /
                <a href="{{ route('register') }}" class="font-medium text-primary hover:underline">{{ __('common.register') }}</a>
            </p>
        @endauth

        <div class="mt-8 divide-y divide-placeholder border-t border-placeholder">
            @forelse ($organization->reviews as $review)
                <div class="py-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder text-xs font-medium text-ink">
                                {{ Str::of($review->user->name)->substr(0, 1)->upper() }}
                            </span>
                            <span class="text-sm font-medium text-ink">{{ $review->user->name }}</span>
                        </div>
                        <x-star-rating :rating="$review->rating" />
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-body-text">{{ $review->comment }}</p>
                    <p class="mt-2 text-xs text-subtle">
                        {{ $review->created_at->translatedFormat('d F, Y') }}
                        @if ($review->branch)
                            · {{ $review->branch->name }}
                        @endif
                    </p>

                    @if ($review->reply)
                        <div class="mt-4 ml-4 border-l-2 border-primary/30 pl-4">
                            <p class="text-xs font-semibold text-ink">{{ __('organizations.org_reply_label', ['name' => $organization->name]) }}</p>
                            <p class="mt-1 text-sm leading-relaxed text-body-text">{{ $review->reply->body }}</p>
                        </div>
                    @endif
                </div>
            @empty
                <p class="py-6 text-sm text-muted">{{ __('organizations.no_reviews') }}</p>
            @endforelse
        </div>
    </section>
@endsection
