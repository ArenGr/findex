<?php

namespace App\Http\Controllers\Organization;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        return view('organizations.dashboard.team.index', [
            'teammates' => $organization->users()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->forceFill([
            'role' => UserRole::ORGANIZATION,
            'organization_id' => $organization->id,
        ])->save();

        return redirect()->route('org.dashboard.team.index')->with('status', 'teammate-added');
    }

    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment. Scoping the
     * lookup through the authenticated organization's own users is also
     * what enforces that an org can only remove its own teammates.
     */
    public function destroy(string $locale, string $user): RedirectResponse
    {
        $currentUser = Auth::guard('organization')->user();
        $organization = $currentUser->organization;
        $teammate = $organization->users()->findOrFail($user);

        // Mirrors AdminResource::canDelete()'s self/last-remaining guard -
        // removing either would either lock the acting user out mid-action
        // or leave the organization with no way to log in at all.
        if ($teammate->is($currentUser) || $organization->users()->count() <= 1) {
            return redirect()->route('org.dashboard.team.index')->with('status', 'teammate-remove-blocked');
        }

        $teammate->delete();

        return redirect()->route('org.dashboard.team.index')->with('status', 'teammate-removed');
    }
}
