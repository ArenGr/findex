<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\QuoteRequest;
use App\Models\QuoteResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuoteTemplateController extends Controller
{
    public function index(): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        return view('organizations.dashboard.quote-templates.index', [
            'templates' => $organization->quoteTemplates()->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('organizations.dashboard.quote-templates.create', [
            'destinations' => QuoteRequest::DESTINATIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;

        $organization->quoteTemplates()->create($this->validated($request));

        return redirect()->route('org.dashboard.quote-templates.index')->with('status', 'quote-template-created');
    }

    /**
     * Resolved manually (not via implicit route-model binding): Laravel's
     * implicit binding does not resolve correctly for a route parameter
     * that comes after a dynamic {locale} prefix segment. Scoping the
     * lookup through the authenticated organization's own templates is
     * also what enforces that an org can only edit its own.
     */
    public function edit(string $locale, string $quoteTemplate): View
    {
        $organization = Auth::guard('organization')->user()->organization;

        return view('organizations.dashboard.quote-templates.edit', [
            'template' => $organization->quoteTemplates()->findOrFail($quoteTemplate),
            'destinations' => QuoteRequest::DESTINATIONS,
        ]);
    }

    public function update(Request $request, string $locale, string $quoteTemplate): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;
        $template = $organization->quoteTemplates()->findOrFail($quoteTemplate);

        $template->update($this->validated($request));

        return redirect()->route('org.dashboard.quote-templates.index')->with('status', 'quote-template-updated');
    }

    public function destroy(string $locale, string $quoteTemplate): RedirectResponse
    {
        $organization = Auth::guard('organization')->user()->organization;
        $organization->quoteTemplates()->findOrFail($quoteTemplate)->delete();

        return redirect()->route('org.dashboard.quote-templates.index')->with('status', 'quote-template-deleted');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'destination_country' => ['nullable', 'string', Rule::in(QuoteRequest::DESTINATIONS)],
            'price_amount' => ['nullable', 'numeric', 'min:0'],
            'price_currency' => ['nullable', Rule::in(QuoteResponse::CURRENCIES)],
            'offered_hotel_name' => ['nullable', 'string', 'max:255'],
            'flight_details' => ['nullable', 'string', 'max:2000'],
            'inclusions' => ['nullable', 'string', 'max:2000'],
            'reply_text' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
