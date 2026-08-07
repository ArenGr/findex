<?php

namespace App\Http\Controllers\Writer\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('writer.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // banned_at is part of the credentials (not a post-login check) so a
        // banned writer can't authenticate at all - same shape as the
        // customer guard's login. EnsureUserIsNotBanned then cuts off any
        // session that was already open when the ban landed.
        if (! Auth::guard('writer')->attempt([...$credentials, 'banned_at' => null, 'role' => UserRole::WRITER], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('writer.dashboard.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('writer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('writer.login');
    }
}
