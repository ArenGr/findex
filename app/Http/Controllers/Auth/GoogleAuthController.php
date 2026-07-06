<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google. The callback route lives outside the {locale}
     * prefix (a single fixed URL is far simpler to register with Google),
     * so the current locale is stashed in the session to restore afterward.
     */
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

        if (!$user) {
            $user = User::where('email', $googleUser->getEmail())->first();
            $isNew = !$user;

            if (!$user) {
                $user = new User([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname(),
                    'email' => $googleUser->getEmail(),
                    'email_verified_at' => now(),
                    // Google-only accounts never use this - a random value
                    // just satisfies the column's NOT NULL constraint.
                    'password' => Str::random(40),
                ]);
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

        Auth::login($user, remember: true);

        return redirect()->intended(route('home', ['locale' => $locale]));
    }
}
