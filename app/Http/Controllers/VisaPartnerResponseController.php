<?php

namespace App\Http\Controllers;

use App\Mail\VisaOfferReceived;
use App\Models\VisaResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The agency's side of a visa request. The token in the link is the only
 * credential, exactly as on the travel flow - see PartnerResponseController.
 */
class VisaPartnerResponseController extends Controller
{
    public function show(string $locale, string $token): View
    {
        $response = VisaResponse::query()
            ->where('response_token', $token)
            ->with(['visaRequest', 'organization'])
            ->first();

        $response?->markViewed();

        return view('visa.respond', ['response' => $response]);
    }

    public function store(Request $request, string $locale, string $token): RedirectResponse
    {
        $response = VisaResponse::query()
            ->where('response_token', $token)
            ->with(['visaRequest', 'organization'])
            ->firstOrFail();

        if (! $response->is_editable) {
            return redirect()->route('visa.respond', ['locale' => $locale, 'token' => $token]);
        }

        if ($request->input('decline') !== null) {
            $response->forceFill(['status' => VisaResponse::STATUS_DECLINED])->save();

            return redirect()->route('visa.respond', ['locale' => $locale, 'token' => $token]);
        }

        $validated = $request->validate([
            'price_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'price_currency' => ['required', Rule::in(VisaResponse::CURRENCIES)],
            'processing_days' => ['nullable', 'integer', 'min:1', 'max:'.VisaResponse::MAX_PROCESSING_DAYS],
            'reply_text' => ['nullable', 'string', 'max:2000'],
            'valid_until' => ['nullable', 'date', 'after:now'],
        ]);

        $wasFirstReply = ! $response->has_replied;

        $response->fill($validated + [
            'status' => VisaResponse::STATUS_RESPONDED,
        ]);

        // A revision keeps the moment the agency first answered.
        if ($wasFirstReply) {
            $response->responded_at = now();
        }

        $response->save();

        $visaRequest = $response->visaRequest;
        $visaRequest->markOffersReceived();

        // Only the first reply is worth an email; revisions are not news.
        if ($wasFirstReply && $visaRequest->requester_email) {
            Mail::to($visaRequest->requester_email)
                ->locale($visaRequest->locale)
                ->send(new VisaOfferReceived($response, $visaRequest->signedResultsUrl()));
        }

        return redirect()->route('visa.respond', ['locale' => $locale, 'token' => $token]);
    }
}
