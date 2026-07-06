@extends('layouts.dashboard')

@section('title', __('org.reviews.title'))

@section('content')
    <h1 class="font-heading text-xl font-semibold text-ink">{{ __('org.reviews.title') }}</h1>

    <div class="mt-6 divide-y divide-placeholder border-t border-placeholder">
        @forelse ($reviews as $review)
            <div class="py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-ink">{{ $review->user->name }}</span>
                        @if ($review->branch)
                            <span class="text-xs text-subtle">· {{ $review->branch->name }}</span>
                        @endif
                    </div>
                    <x-star-rating :rating="$review->rating" />
                </div>
                <p class="mt-3 text-sm leading-relaxed text-body-text">{{ $review->comment }}</p>
                <p class="mt-2 text-xs text-subtle">{{ $review->created_at->translatedFormat('d F, Y') }}</p>

                <form method="POST" action="{{ route('org.dashboard.reviews.reply', $review) }}" class="mt-4">
                    @csrf

                    <label for="body-{{ $review->id }}" class="block text-sm font-medium text-ink">{{ __('org.reviews.reply_label') }}</label>
                    <textarea
                        name="body"
                        id="body-{{ $review->id }}"
                        rows="3"
                        placeholder="{{ __('org.reviews.reply_placeholder') }}"
                        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                    >{{ old('body', $review->reply->body ?? '') }}</textarea>

                    <button type="submit" class="mt-3 bg-primary px-5 py-2 text-sm font-medium text-white hover:bg-primary-dark">
                        {{ $review->reply ? __('org.reviews.update_reply_button') : __('org.reviews.reply_button') }}
                    </button>
                </form>
            </div>
        @empty
            <p class="py-6 text-sm text-muted">{{ __('org.reviews.no_reviews') }}</p>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $reviews->links() }}
    </div>
@endsection
