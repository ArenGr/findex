<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\ReportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportRequestController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        return view('organizations.dashboard.reports.index', [
            'reportRequests' => $organization->reportRequests()->with(['report', 'branch'])->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        return view('organizations.dashboard.reports.create', [
            'branches' => $organization->branches()->active()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;

        $validated = $request->validate([
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $organization->id),
            ],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date', 'after_or_equal:period_from'],
        ]);

        $reportRequest = $organization->reportRequests()->create([
            ...$validated,
            'status' => 'pending',
        ]);

        GenerateReportJob::dispatch($reportRequest);

        return redirect()->route('org.dashboard.reports.index')->with('status', 'report-requested');
    }

    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment. Scoping the
     * lookup through the authenticated organization's own report requests
     * is also what enforces that an org can only view its own reports.
     */
    public function show(string $locale, string $reportRequest): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        /** @var ReportRequest $reportRequest */
        $reportRequest = $organization->reportRequests()->with(['report', 'branch'])->findOrFail($reportRequest);

        return view('organizations.dashboard.reports.show', [
            'reportRequest' => $reportRequest,
        ]);
    }
}
