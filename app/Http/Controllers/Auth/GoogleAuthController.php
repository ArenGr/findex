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
                    // Google-only accounts never use this - a random value
                    // just satisfies the column's NOT NULL constraint.
                    'password' => Str::random(40),
                ]);
                // Not mass-assignable (deliberately absent from $fillable,
                // like banned_at) - set directly instead.
                $user->email_verified_at = now();
            } else {
                // An existing password-based account is being linked to
                // Google for the first time. Google has just authoritatively
                // proven ownership of this email address, which a
                // pre-existing password may not have - e.g. someone could
                // have pre-registered this email with a password only they
                // know, waiting for the real owner to eventually "Sign in
                // with Google" and inherit access to that account. Rotating
                // the password here revokes any such squatted password the
                // moment the real owner is verified, without requiring a
                // full email-verification flow. The legitimate user is
                // unaffected since they're about to be logged in via Google
                // anyway and can set a new password later if they want one.
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

        Auth::login($user, remember: true);

        return redirect()->intended(route('home', ['locale' => $locale]));
    }
}
