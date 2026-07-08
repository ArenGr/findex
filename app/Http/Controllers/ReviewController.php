<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment.
     */
    public function store(Request $request, string $locale, string $organization): RedirectResponse
    {
        $organization = Organization::active()->where('slug', $organization)->firstOrFail();

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $organization->id),
            ],
        ]);

        // One review per user per organization - resubmitting updates it.
        Review::updateOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $request->user()->id],
            $validated
        );

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'review-submitted');
    }
}
