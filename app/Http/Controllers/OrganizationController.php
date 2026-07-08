<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    /**
     * Public directory of all organizations, filterable by type and sorted
     * by average rating (highest first) so the best-reviewed organizations
     * surface first.
     */
    public function index(string $locale, Request $request): View
    {
        $type = $request->string('type')->value();
        $types = array_keys(__('organizations.types'));

        if (!in_array($type, $types, true)) {
            $type = null;
        }

        $organizations = Organization::active()
            ->withRatingStats()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('organizations.index', [
            'organizations' => $organizations,
            'types' => $types,
            'activeType' => $type,
        ]);
    }

    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment.
     */
    public function show(string $locale, string $organization): View
    {
        $organization = Organization::active()->where('slug', $organization)->firstOrFail();

        $organization->load(['reviews.user', 'reviews.reply', 'reviews.branch', 'branches' => fn ($query) => $query->active()]);

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
