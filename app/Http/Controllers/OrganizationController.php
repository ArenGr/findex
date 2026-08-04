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

        $search = $request->string('q')->trim()->value();

        $organizations = Organization::active()
            ->withRatingStats()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($search, fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('organizations.index', [
            'organizations' => $organizations,
            'types' => $types,
            'activeType' => $type,
            'search' => $search,
        ]);
    }

    /**
     * SEO landing page for a single organization type - unlike index()'s
     * type filter (a query string on a generic directory), this is a
     * dedicated URL with its own title/meta description/intro copy, so it
     * can actually rank for "banks in armenia" style searches instead of
     * competing with the unfiltered directory for the same content.
     */
    public function banks(Request $request): View
    {
        return $this->categoryPage(
            request: $request,
            type: 'bank',
            heading: __('banks.heading'),
            subtitle: __('banks.subtitle'),
            metaTitle: __('meta.banks_title'),
            metaDescription: __('meta.banks_description'),
            statLabel: __('banks.stat_count'),
            ctaLabel: __('banks.cta_compare'),
            ctaRoute: route('organizations.compare'),
            showCompare: true,
        );
    }

    public function travelAgencies(Request $request): View
    {
        return $this->categoryPage(
            request: $request,
            type: 'tourism',
            heading: __('travel_agencies.heading'),
            subtitle: __('travel_agencies.subtitle'),
            metaTitle: __('meta.travel_agencies_title'),
            metaDescription: __('meta.travel_agencies_description'),
            statLabel: __('travel_agencies.stat_count'),
            ctaLabel: __('travel_agencies.cta_quote'),
            ctaRoute: route('tourism.request'),
            showCompare: false,
        );
    }

    private function categoryPage(
        Request $request,
        string $type,
        string $heading,
        string $subtitle,
        string $metaTitle,
        string $metaDescription,
        string $statLabel,
        string $ctaLabel,
        string $ctaRoute,
        bool $showCompare,
    ): View {
        $search = $request->string('q')->trim()->value();

        $organizations = Organization::active()
            ->withRatingStats()
            ->where('type', $type)
            ->when($search, fn ($query) => $query->where('name', 'like', '%' . $search . '%'))
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('organizations.category', compact(
            'organizations',
            'heading',
            'subtitle',
            'metaTitle',
            'metaDescription',
            'statLabel',
            'ctaLabel',
            'ctaRoute',
            'showCompare',
            'search',
        ));
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
