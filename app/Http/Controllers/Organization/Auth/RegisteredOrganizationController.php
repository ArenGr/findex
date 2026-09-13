<?php

namespace App\Http\Controllers\Organization\Auth;

use App\Enums\UserRole;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Http\Controllers\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\AdminNotifier;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredOrganizationController extends Controller
{
    use GeneratesUniqueSlug;

    public const TYPES = Organization::TYPES;

    public function create(): View
    {
        return view('organizations.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', ValidationRules::email(), 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'type' => ['required', Rule::in(self::TYPES)],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $organization = Organization::create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name'], Organization::class),
                'type' => $validated['type'],
                'website' => $validated['website'] ?? null,
                'country_code' => 'AM',
                'is_active' => false,
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

            AdminNotifier::pendingApproval(
                title: 'New organization awaiting approval',
                body: "{$organization->name} just registered and is inactive until approved.",
                icon: 'heroicon-o-building-office-2',
                reviewUrl: OrganizationResource::getUrl('edit', ['record' => $organization->getKey()]),
            );

            return $user;
        });

        $user->sendEmailVerificationNotification();

        Auth::guard('organization')->login($user);

        $request->session()->regenerate();

        return redirect()->route('org.dashboard.index');
    }
}
