<?php

namespace App\Http\Controllers;

use App\Models\DestinationAlert;
use App\Models\QuoteRequest;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DestinationAlertController extends Controller
{
    /**
     * Reachable from the "no partner for this destination yet" state on
     * the trip request form - lets a visitor leave their email instead of
     * just bouncing off the site.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'destination_country' => ['required', 'string', Rule::in(QuoteRequest::DESTINATIONS)],
            'email' => [Rule::requiredIf(!$request->user()), 'nullable', ValidationRules::email(), 'max:255'],
        ]);

        DestinationAlert::updateOrCreate(
            [
                'email' => $request->user()?->email ?? $validated['email'],
                'destination_country' => $validated['destination_country'],
            ],
            [
                'user_id' => $request->user()?->id,
                'locale' => app()->getLocale(),
            ]
        );

        return back()->with('status', 'destination-alert-created');
    }

    /**
     * Reached from the signed unsubscribe link in DestinationNowAvailable's
     * email footer. Clears every destination alert for this email rather
     * than just the one that triggered the send, since a guest with
     * several alerts pending has no account to manage them individually
     * from.
     */
    public function unsubscribe(Request $request, string $locale)
    {
        $email = $request->query('email');

        DestinationAlert::where('email', $email)->delete();

        return view('destination-alerts.unsubscribed');
    }
}
