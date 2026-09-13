<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    // Redirect to Google.
    public function redirect(): RedirectResponse
    {
        session(['auth.google.locale' => app()->getLocale()]);

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $locale = session()->pull('auth.google.locale', config('localization.default'));

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login', ['locale' => $locale])
                ->withErrors(['email' => __('auth.google_failed')]);
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user && ! $user->isCustomer()) {
                return redirect()->route('login', ['locale' => $locale])
                    ->withErrors(['email' => __('auth.failed')]);
            }

            $isNew = ! $user;

            if (! $user) {
                $user = new User([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname(),
                    'email' => $googleUser->getEmail(),
                    'password' => Str::random(40),
                ]);
                // Not mass-assignable (deliberately absent from $fillable, like banned_at) - set directly instead.
                $user->email_verified_at = now();
                $user->role = UserRole::CUSTOMER;
            } else {
                // An existing password-based account is being linked to Google for the first time.
                $user->password = Str::random(40);
                $user->email_verified_at ??= now();
            }

            $user->google_id = $googleUser->getId();
            $user->avatar = $googleUser->getAvatar();
            $user->save();

            if ($isNew) {
                event(new Registered($user));
            }
        }

        if ($user->isBanned()) {
            return redirect()->route('login', ['locale' => $locale])
                ->withErrors(['email' => __('auth.failed')]);
        }

        if (! $user->isCustomer()) {
            return redirect()->route('login', ['locale' => $locale])
                ->withErrors(['email' => __('auth.failed')]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('home', ['locale' => $locale]));
    }
}
