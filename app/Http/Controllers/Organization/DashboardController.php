<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\QuoteResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        return view('organizations.dashboard.index', [
            'organization' => $organization,
            'reviewsCount' => $organization->reviews()->count(),
            'averageRating' => $organization->reviews()->avg('rating'),
            'unrepliedCount' => $organization->reviews()->whereDoesntHave('reply')->count(),
            'branchesCount' => $organization->branches()->count(),
            'recentReportRequests' => $organization->reportRequests()->with('report')->latest()->take(5)->get(),
            'tourismStats' => $organization->hasTourismPage() ? $this->tourismStats($organization) : null,
        ]);
    }

    /**
     * Response rate / avg response time, shown on the overview page so a
     * tourism partner has a reason to check the dashboard beyond just
     * reacting to individual Telegram pings. The underlying numbers live
     * on Organization (see avgQuoteResponseTimeHours()/quoteResponseRate())
     * since the public profile page's badges (isFastResponder()) need the
     * same figures.
     */
    private function tourismStats(Organization $organization): array
    {
        return [
            'total' => $organization->quoteResponses()->count(),
            'declined' => $organization->quoteResponses()->where('status', QuoteResponse::STATUS_DECLINED)->count(),
            'responseRate' => $organization->quoteResponseRate(),
            'avgResponseTimeHours' => $organization->avgQuoteResponseTimeHours(),
        ];
    }
}
