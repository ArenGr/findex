<?php

namespace App\Http\Controllers;

use App\Models\DestinationAlert;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\Intl\Countries;

class DestinationAlertController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'destination_country' => ['required', 'string', Rule::in(array_keys(Countries::getNames()))],
            'email' => [Rule::requiredIf(! $request->user()), 'nullable', ValidationRules::email(), 'max:255'],
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

    // Reached from the signed unsubscribe link in DestinationNowAvailable's email footer.
    public function unsubscribe(Request $request, string $locale)
    {
        $email = $request->query('email');

        DestinationAlert::where('email', $email)->delete();

        return view('destination-alerts.unsubscribed');
    }
}
