<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment.
     */
    public function show(string $locale, string $organization): View
    {
        $organization = Organization::where('slug', $organization)->firstOrFail();

        $organization->load(['reviews.user']);

        $myReview = auth()->check()
            ? $organization->reviews->firstWhere('user_id', auth()->id())
            : null;

        return view('organizations.show', [
            'organization' => $organization,
            'averageRating' => $organization->reviews->avg('rating'),
            'reviewsCount' => $organization->reviews->count(),
            'myReview' => $myReview,
        ]);
    }
}
