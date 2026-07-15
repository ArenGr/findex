<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InsuranceController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        $quotes = $organization->autoInsuranceQuotes()
            ->with('autoInsuranceRequest')
            ->latest()
            ->get();

        $interestedCount = $quotes->filter(fn ($quote) => $quote->is_interested)->count();

        return view('organizations.dashboard.insurance.index', [
            'organization' => $organization,
            'quotes' => $quotes,
            'totalQuotes' => $quotes->count(),
            'interestedCount' => $interestedCount,
            'interestedRate' => $quotes->isNotEmpty() ? round($interestedCount / $quotes->count() * 100) : null,
        ]);
    }
}
