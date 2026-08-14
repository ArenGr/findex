<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Organization;
use App\Services\OrganizationRatesData;
use App\Services\RateHistoryService;
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

        if (! in_array($type, $types, true)) {
            $type = null;
        }

        $search = $request->string('q')->trim()->value();

        $organizations = Organization::active()
            ->withRatingStats()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
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

    public function insuranceCompanies(Request $request): View
    {
        return $this->categoryPage(
            request: $request,
            type: 'insurance',
            heading: __('auto_insurance.companies.heading'),
            subtitle: __('auto_insurance.companies.subtitle'),
            metaTitle: __('meta.insurance_companies_title'),
            metaDescription: __('meta.insurance_companies_description'),
            statLabel: __('auto_insurance.companies.stat_count'),
            ctaLabel: __('auto_insurance.companies.cta_quote'),
            ctaRoute: route('insurance.auto.request'),
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
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
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
    public function show(string $locale, string $organization, OrganizationRatesData $ratesData, RateHistoryService $history): View
    {
        $organization = Organization::active()->where('slug', $organization)->firstOrFail();

        $organization->load(['reviews.user', 'reviews.reply', 'reviews.branch', 'branches' => fn ($query) => $query->active()]);

        $myReview = auth()->check()
            ? $organization->reviews->firstWhere('user_id', auth()->id())
            : null;

        // Banks and exchange offices publish rates; travel agencies and
        // insurers do not, and asking for theirs would be a guaranteed empty
        // section on every one of their pages.
        $rates = $organization->hasRatesPage()
            ? $ratesData->build($organization)
            : ['groups' => [], 'updated_at' => null, 'currency_count' => 0];

        // A trend for the organization's headline currency. Cash only, and only
        // when there is more than a single point - a chart of one day is a dot.
        $historyCurrency = null;
        $historySeries = [];
        $historyDays = $history->offerableRanges()[0];

        if ($organization->hasRatesPage() && $rates['groups'] !== []) {
            $first = collect($rates['groups'])->first()[0] ?? null;
            $historyCurrency = $first === null ? null : Currency::where('code', $first['code'])->first();

            if ($historyCurrency !== null) {
                $historySeries = $history->organizationSeries(
                    $organization->id,
                    $historyCurrency->id,
                    RateType::CASH,
                    $historyDays,
                );
            }

            if (count($historySeries) < 2) {
                $historySeries = [];
            }
        }

        return view('organizations.show', [
            'organization' => $organization,
            'averageRating' => $organization->reviews->avg('rating'),
            'reviewsCount' => $organization->reviews->count(),
            'myReview' => $myReview,
            'rates' => $rates,
            // Only exchange offices negotiate walk-in cash, and only once they
            // are reachable on Telegram - the same rule the fan-out job uses,
            // so the page cannot offer something the job would drop.
            // This organization's own rate over time, for whichever currency
            // it quotes first - one chart, not eleven. The full picture lives
            // on the history page.
            'historyCurrency' => $historyCurrency ?? null,
            'historySeries' => $historySeries ?? [],
            'historyDays' => $historyDays ?? 0,
            // Same modal as /rates, so "Get a better rate" means one thing
            // and behaves one way wherever it is pressed.
            'quoteCurrencies' => Currency::where('is_active', true)
                ->whereIn('code', array_keys(config('exchange-quotes.minimum_amounts')))
                ->orderBy('sort_order')
                ->get(),
            'quoteCities' => Branch::query()->whereNotNull('city')->where('is_active', true)
                ->distinct()->orderBy('city')->pluck('city')->all(),
            'canNegotiate' => $organization->type === 'exchange'
                && $organization->telegram_chat_id !== null
                && $rates['groups'] !== [],
        ]);
    }
}
