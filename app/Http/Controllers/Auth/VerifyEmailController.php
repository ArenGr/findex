<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    public function verify(Request $request, string $locale, int $id, string $hash): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()
            ->route(match (true) {
                $user->isOrganization() => 'org.dashboard.index',
                $user->isWriter() => 'writer.dashboard.index',
                default => 'home',
            }, ['locale' => $locale])
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

    public function resendForWriter(): RedirectResponse
    {
        return $this->resend(Auth::guard('writer')->user());
    }

    private function resend(User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', 'verification-link-sent');
    }
}
