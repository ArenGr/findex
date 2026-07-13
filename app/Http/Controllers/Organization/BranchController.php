<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        return view('organizations.dashboard.branches.index', [
            'branches' => $organization->branches()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('organizations.dashboard.branches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;

        $validated = $this->validated($request);
        $validated['is_active'] = $request->boolean('is_active', true);

        $organization->branches()->create($validated);

        return redirect()->route('org.dashboard.branches.index')->with('status', 'branch-created');
    }

    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment. Scoping the
     * lookup through the authenticated organization's own branches is also
     * what enforces that an org can only edit its own branches.
     */
    public function edit(string $locale, string $branch): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        return view('organizations.dashboard.branches.edit', [
            'branch' => $organization->branches()->findOrFail($branch),
        ]);
    }

    public function update(Request $request, string $locale, string $branch): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;
        $branch = $organization->branches()->findOrFail($branch);

        $validated = $this->validated($request);
        $validated['is_active'] = $request->boolean('is_active');

        $branch->update($validated);

        return redirect()->route('org.dashboard.branches.index')->with('status', 'branch-updated');
    }

    public function destroy(string $locale, string $branch): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;
        $organization->branches()->findOrFail($branch)->delete();

        return redirect()->route('org.dashboard.branches.index')->with('status', 'branch-deleted');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
