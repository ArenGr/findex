<?php

namespace App\Http\Controllers\Organization\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredOrganizationController extends Controller
{
    public const TYPES = ['bank', 'exchange', 'insurance', 'other'];

    public function create(): View
    {
        return view('organizations.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:organizations,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'type' => ['required', Rule::in(self::TYPES)],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        $organization = Organization::create([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'type' => $validated['type'],
            'website' => $validated['website'] ?? null,
            'country_code' => 'AM',
            'is_active' => false,
        ]);

        Auth::guard('organization')->login($organization);

        $request->session()->regenerate();

        return redirect()->route('org.dashboard.index');
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
