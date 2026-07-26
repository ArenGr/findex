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

class AppleAuthController extends Controller
{
    /**
     * Redirect to Apple. The callback route lives outside the {locale}
     * prefix (a single fixed URL is far simpler to register with Apple),
     * so the current locale is stashed in the session to restore afterward.
     */
    public function redirect(): RedirectResponse
    {
        // Hidden from the login/register pages until real credentials are
        // configured (see the button's @if in auth/register.blade.php and
        // auth/login.blade.php) - this guards the route directly too, since
        // hitting it unconfigured would otherwise throw while Socialite
        // tries to sign a client-secret JWT with an empty private key.
        abort_unless(config('services.apple.client_id'), 404);

        session(['auth.apple.locale' => app()->getLocale()]);

        return Socialite::driver('apple')->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(config('services.apple.client_id'), 404);

        $locale = session()->pull('auth.apple.locale', config('localization.default'));

        try {
            $appleUser = Socialite::driver('apple')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login', ['locale' => $locale])
                ->withErrors(['email' => __('auth.apple_failed')]);
        }

        $user = User::where('apple_id', $appleUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $appleUser->getEmail())->first();

            // Bail before the "link existing account" branch below rotates
            // this row's password - since organization/admin accounts now
            // live in the same users table (see App\Enums\UserRole), an
            // email collision could otherwise silently break a non-customer
            // account's password just by someone attempting Apple login
            // with their email, even though the login itself is rejected.
            if ($user && ! $user->isCustomer()) {
                return redirect()->route('login', ['locale' => $locale])
                    ->withErrors(['email' => __('auth.failed')]);
            }

            $isNew = ! $user;

            if (! $user) {
                $user = new User([
                    // Apple only ever sends a name on the account's very
                    // first authorization, never again after - this branch
                    // only runs when no matching user exists yet, so that's
                    // exactly the case this applies to. No nickname
                    // equivalent to fall back to like Google, so fall back
                    // to the email's local part instead of leaving it blank.
                    'name' => $appleUser->getName() ?: Str::before($appleUser->getEmail(), '@'),
                    'email' => $appleUser->getEmail(),
                    // Apple-only accounts never use this - a random value
                    // just satisfies the column's NOT NULL constraint.
                    'password' => Str::random(40),
                ]);
                // Not mass-assignable (deliberately absent from $fillable,
                // like banned_at) - set directly instead. role is set
                // explicitly rather than left to the column's DB default
                // so the in-memory $user->isCustomer() check below (right
                // after save()) reads correctly without a refresh().
                $user->email_verified_at = now();
                $user->role = UserRole::CUSTOMER;
            } else {
                // An existing password-based account is being linked to
                // Apple for the first time. Apple has just authoritatively
                // proven ownership of this email address, which a
                // pre-existing password may not have - e.g. someone could
                // have pre-registered this email with a password only they
                // know, waiting for the real owner to eventually "Sign in
                // with Apple" and inherit access to that account. Rotating
                // the password here revokes any such squatted password the
                // moment the real owner is verified, without requiring a
                // full email-verification flow. The legitimate user is
                // unaffected since they're about to be logged in via Apple
                // anyway and can set a new password later if they want one.
                $user->password = Str::random(40);
                $user->email_verified_at ??= now();
            }

            // Apple never provides a profile picture, unlike Google's
            // getAvatar() - nothing to set here.
            $user->apple_id = $appleUser->getId();
            $user->save();

            if ($isNew) {
                event(new Registered($user));
            }
        }

        if ($user->isBanned()) {
            return redirect()->route('login', ['locale' => $locale])
                ->withErrors(['email' => __('auth.failed')]);
        }

        // Guards against a customer-facing Apple login resolving to an
        // organization/admin account whose email happens to match - now
        // that all three roles share one users table, matching by email
        // alone (the $user lookups above) isn't enough on its own.
        if (! $user->isCustomer()) {
            return redirect()->route('login', ['locale' => $locale])
                ->withErrors(['email' => __('auth.failed')]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('home', ['locale' => $locale]));
    }
}
