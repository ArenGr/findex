<?php

namespace App\Http\Controllers\Organization;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ValidationRules;
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
            'email' => ['required', 'string', 'lowercase', ValidationRules::email(), 'max:255', 'unique:users,email'],
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

    public function destroy(string $locale, string $user): RedirectResponse
    {
        $currentUser = Auth::guard('organization')->user();
        $organization = $currentUser->organization;
        $teammate = $organization->users()->findOrFail($user);

        if ($teammate->is($currentUser) || $organization->users()->count() <= 1) {
            return redirect()->route('org.dashboard.team.index')->with('status', 'teammate-remove-blocked');
        }

        $teammate->delete();

        return redirect()->route('org.dashboard.team.index')->with('status', 'teammate-removed');
    }
}
