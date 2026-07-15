<?php

namespace App\Http\Controllers\Organization\Auth;

use App\Enums\UserRole;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredOrganizationController extends Controller
{
    public const TYPES = Organization::TYPES;

    public function create(): View
    {
        return view('organizations.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'type' => ['required', Rule::in(self::TYPES)],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        // Organization (business profile) and User (login, role=organization)
        // must both be created or neither is - see Organization::users() /
        // User::organization().
        $user = DB::transaction(function () use ($validated) {
            $organization = Organization::create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
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

            $this->notifyAdminsOfPendingApproval($organization);

            return $user;
        });

        $user->sendEmailVerificationNotification();

        Auth::guard('organization')->login($user);

        $request->session()->regenerate();

        return redirect()->route('org.dashboard.index');
    }

    /**
     * New organizations register inactive (see is_active above) and need an
     * admin to review and approve them - surfaced via the admin panel's
     * topbar notification bell (see AdminPanelProvider::databaseNotifications())
     * rather than relying on an admin to notice by browsing the list.
     */
    private function notifyAdminsOfPendingApproval(Organization $organization): void
    {
        Notification::make()
            ->title('New organization awaiting approval')
            ->body("{$organization->name} just registered and is inactive until approved.")
            ->icon('heroicon-o-building-office-2')
            ->actions([
                Action::make('review')
                    ->label('Review')
                    // OrganizationResource deliberately routes admin pages by
                    // id ($recordRouteKeyName = 'id'), unlike the model's own
                    // slug-based getRouteKeyName() used for public routes -
                    // passing the model instance here would build the URL
                    // from the slug instead, which the resource can't resolve.
                    ->url(OrganizationResource::getUrl('edit', ['record' => $organization->getKey()])),
            ])
            ->sendToDatabase(User::where('role', UserRole::ADMIN)->get());
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $suffix = 2;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
