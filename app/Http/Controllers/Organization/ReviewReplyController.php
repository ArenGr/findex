<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\ReviewReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ReviewReplyController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user();

        $reviews = $organization->reviews()
            ->with(['user', 'branch', 'reply'])
            ->latest()
            ->paginate(15);

        return view('organizations.dashboard.reviews', [
            'organization' => $organization,
            'reviews' => $reviews,
        ]);
    }

    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment. Scoping the
     * lookup through the authenticated organization's own reviews is also
     * what enforces that an org can only reply to its own reviews.
     */
    public function store(Request $request, string $locale, string $review): RedirectResponse
    {
        $organization = Auth::guard('organization')->user();
        $review = $organization->reviews()->findOrFail($review);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        ReviewReply::updateOrCreate(
            ['review_id' => $review->id],
            ['organization_id' => $organization->id, 'body' => $validated['body']]
        );

        return redirect()->route('org.dashboard.reviews.index')->with('status', 'reply-submitted');
    }
}
