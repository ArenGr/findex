<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function store(Request $request, string $locale, string $organization): RedirectResponse
    {
        $organization = Organization::active()->where('slug', $organization)->firstOrFail();

        // Honeypot: a real visitor never sees or fills this field (hidden via CSS in the form).
        if ($request->filled('company')) {
            return redirect()
                ->route('organizations.show', $organization)
                ->with('status', 'review-submitted');
        }

        if ($request->user() && ! $request->user()->hasVerifiedEmail()) {
            return redirect()
                ->route('organizations.show', $organization)
                ->with('status', 'email-verification-required');
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
