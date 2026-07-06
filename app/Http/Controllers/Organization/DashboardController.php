<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user();

        return view('organizations.dashboard.index', [
            'organization' => $organization,
            'reviewsCount' => $organization->reviews()->count(),
            'averageRating' => $organization->reviews()->avg('rating'),
            'unrepliedCount' => $organization->reviews()->whereDoesntHave('reply')->count(),
            'branchesCount' => $organization->branches()->count(),
            'recentReportRequests' => $organization->reportRequests()->with('report')->latest()->take(5)->get(),
        ]);
    }
}
