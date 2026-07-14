<?php

namespace App\Http\Controllers;

use App\Models\DestinationAlert;
use App\Models\QuoteRequest;
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
            'email' => [Rule::requiredIf(!$request->user()), 'nullable', 'email', 'max:255'],
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
}
