<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment.
     */
    public function store(Request $request, string $locale, string $organization): RedirectResponse
    {
        $organization = Organization::active()->where('slug', $organization)->firstOrFail();

        // Honeypot: a real visitor never sees or fills this field (hidden via
        // CSS in the form). A bot filling every input trips it. Pretend to
        // succeed so it doesn't learn the check exists.
        if ($request->filled('company')) {
            return redirect()
                ->route('organizations.show', $organization)
                ->with('status', 'review-submitted');
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:10', 'max:2000'],
            'guest_name' => [Rule::requiredIf(! $request->user()), 'nullable', 'string', 'min:2', 'max:60'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('organization_id', $organization->id),
            ],
        ], attributes: [
            'guest_name' => __('organizations.name_attribute'),
        ]);

        if ($request->user()) {
            // One review per account per organization - resubmitting updates it.
            Review::updateOrCreate(
                ['organization_id' => $organization->id, 'user_id' => $request->user()->id],
                collect($validated)->except('guest_name')->all()
            );
        } else {
            // Guest reviews have no account to dedupe against, so each
            // submission is its own row - rate limiting (see routes/web.php)
            // is the abuse guard here instead of the unique constraint.
            Review::create([
                'organization_id' => $organization->id,
                ...$validated,
            ]);
        }

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'review-submitted');
    }
}
