<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function index(Request $request): View
    {
        $keys = ApiKey::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->withSum(['usages as requests_this_month' => fn ($query) => $query->where('day', '>=', now()->startOfMonth())], 'requests')
            ->latest()
            ->get();

        return view('api.keys', [
            'keys' => $keys,
            'plans' => config('api.plans'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            // Only the plans a customer can actually sign up to.
            'plan' => ['required', Rule::in(['free'])],
        ]);

        [, $token] = ApiKey::issue([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'plan' => $validated['plan'],
        ]);

        return redirect()->route('api.keys.index')->with('new_api_key', $token);
    }

    public function destroy(Request $request, string $locale, ApiKey $apiKey): RedirectResponse
    {
        abort_unless($apiKey->user_id === $request->user()->id, 403);

        $apiKey->forceFill(['revoked_at' => now()])->save();

        return redirect()->route('api.keys.index')->with('status', 'api-key-revoked');
    }
}
