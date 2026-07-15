<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Guard-agnostic on purpose: the signed link a customer or organization
 * user gets emailed (see User::sendEmailVerificationNotification()) may be
 * opened in a browser/session that isn't currently logged in on the
 * matching guard - or logged in at all. Rather than Laravel's default
 * EmailVerificationRequest (which requires Auth::user() on whichever guard
 * happens to be active), this authenticates the request itself via the
 * signature + email hash, the same "possession of the link is the
 * credential" model already used for PartnerResponseController and
 * QuoteRequest::signedResultsUrl().
 */
class VerifyEmailController extends Controller
{
    public function verify(Request $request, string $locale, int $id, string $hash): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()
            ->route($user->isOrganization() ? 'org.dashboard.index' : 'home', ['locale' => $locale])
            ->with('status', 'email-verified');
    }

    public function resendForCustomer(Request $request): RedirectResponse
    {
        return $this->resend($request->user());
    }

    public function resendForOrganization(): RedirectResponse
    {
        return $this->resend(Auth::guard('organization')->user());
    }

    private function resend(User $user): RedirectResponse
    {
        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'verification-link-sent');
    }
}
