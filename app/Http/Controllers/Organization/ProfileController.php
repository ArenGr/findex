<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('organizations.dashboard.profile', [
            'organization' => Auth::guard('organization')->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = Auth::guard('organization')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = Storage::disk('public')->url(
                $request->file('logo')->store('organizations/logos', 'public')
            );
        } else {
            unset($validated['logo']);
        }

        $organization->update($validated);

        return redirect()->route('org.dashboard.profile.edit')->with('status', 'profile-updated');
    }
}
