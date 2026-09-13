<?php

namespace App\Http\Controllers;

use App\Enums\RateType;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\ExchangeQuoteRequest;
use App\Models\ExchangeQuoteResponse;
use App\Models\Organization;
use App\Services\OrganizationRatesData;
use App\Services\RateHistoryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganizationController extends Controller
{
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
            showCompare: true,
            view: 'organizations.insurance-companies',
        );
    }

    // How a category listing may be ordered.
    public const CATEGORY_SORTS = ['rated', 'reviewed', 'name'];

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
        // Insurance has its own listing; banks and travel agencies share the plain one.
        string $view = 'organizations.category',
    ): View {
        $search = $request->string('q')->trim()->value();
        $sort = in_array($request->query('sort'), self::CATEGORY_SORTS, true) ? $request->query('sort') : 'rated';

        $organizations = Organization::active()
            ->withRatingStats()
            // So a row can say how many branches an organization has without a query per row.
            ->withCount(['branches' => fn ($query) => $query->active()])
            ->where('type', $type)
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when(
                $sort === 'name',
                fn ($query) => $query->orderBy('name'),
                fn ($query) => $query
                    ->when(
                        $sort === 'reviewed',
                        fn ($query) => $query->orderByDesc('reviews_count')->orderByDesc('reviews_avg_rating'),
                        fn ($query) => $query->orderByDesc('reviews_avg_rating')->orderByDesc('reviews_count'),
                    )
                    ->orderBy('name'),
            )
            ->paginate(20)
            ->withQueryString();

        return view($view, compact(
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
            'sort',
        ));
    }

    public function show(Request $request, string $locale, string $organization, OrganizationRatesData $ratesData, RateHistoryService $history): View
    {
        $organization = Organization::active()->where('slug', $organization)->firstOrFail();

        $organization->load(['reviews.user', 'reviews.reply', 'reviews.branch', 'branches' => fn ($query) => $query->active()]);

        $myReview = auth()->check()
            ? $organization->reviews->firstWhere('user_id', auth()->id())
            : null;

        $rates = $organization->hasRatesPage()
            ? $ratesData->build($organization)
            : ['groups' => [], 'updated_at' => null, 'currency_count' => 0];

        // A trend for the organization's headline currency.
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

        // Where to go next when this organization is not the answer.
        $similar = Organization::active()
            ->where('type', $organization->type)
            ->whereKeyNot($organization->id)
            ->withAvg('reviews as reviews_avg_rating', 'rating')
            ->when($organization->hasRatesPage(), fn ($query) => $query
                ->whereHas('currencyRates'))
            ->inRandomOrder()
            ->limit(3)
            ->get();

        $headlineCode = (string) (collect($rates['groups'])->first()[0]['code'] ?? 'USD');

        $similarRates = CurrencyRate::query()
            ->whereIn('organization_id', $similar->pluck('id'))
            ->where('rate_type', RateType::CASH)
            ->whereHas('currency', fn ($query) => $query->where('code', $headlineCode))
            ->get()
            ->keyBy('organization_id');

        return view('organizations.show', [
            'organization' => $organization,
            'averageRating' => $organization->reviews->avg('rating'),
            'reviewsCount' => $organization->reviews->count(),
            'myReview' => $myReview,
            'similar' => $similar,
            'similarRates' => $similarRates,
            'headlineCode' => $headlineCode,
            'rates' => $rates,
            'historyCurrency' => $historyCurrency ?? null,
            'historySeries' => $historySeries ?? [],
            'historyDays' => $historyDays ?? 0,
            'quoteCurrencies' => Currency::where('is_active', true)
                ->whereIn('code', array_keys(config('exchange-quotes.minimum_amounts')))
                ->orderBy('sort_order')
                ->get(),
            'quoteCities' => Branch::query()->whereNotNull('city')->where('is_active', true)
                ->distinct()->orderBy('city')->pluck('city')->all(),
            'activeQuoteRequest' => $this->activeQuoteRequest($request),
            'canNegotiate' => $organization->type === 'exchange'
                && $organization->telegram_chat_id !== null
                && $rates['groups'] !== [],
        ]);
    }

    // The viewer's own open better-rate request, if there is one.
    private function activeQuoteRequest(Request $request): ?array
    {
        $quoteRequest = $request->user()
            ? ExchangeQuoteRequest::where('user_id', $request->user()->id)->open()->latest('id')->first()
            : ExchangeQuoteRequest::find($request->session()->get('exchange.active_request.id'));

        if ($quoteRequest === null || ! $quoteRequest->is_open) {
            return null;
        }

        // An accepted offer is a settled deal, not an open question.
        if ($quoteRequest->responses()->where('status', ExchangeQuoteResponse::STATUS_ACCEPTED)->exists()) {
            return null;
        }

        return [
            'amount' => (float) $quoteRequest->amount,
            'currency' => $quoteRequest->currency->code,
            'asked' => $quoteRequest->created_at->diffForHumans(),
            'url' => $request->user()
                ? route('exchange.show', $quoteRequest)
                : ($request->session()->get('exchange.active_request.url') ?? $quoteRequest->signedResultsUrl()),
        ];
    }
}
