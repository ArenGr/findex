<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

/**
 * Live compulsory motor TPL premiums from Armenia Insurance.
 *
 *     POST /api/aswa
 *     {"plateNumber":..,"idNumber":..,"startDate":"YYYYMMDD","endDate":"YYYYMMDD","useType":"OWN"}
 *     -> 201 {"responseData":{"errorCode":"0","errorText":"...","premium":"44000"}}
 *
 * The endpoint's name is the interesting part. "ASWA" also shows up as
 * Sil Insurance's `aswaToken`, and the data behind it - the registered
 * owner's name, the vehicle's make and model, its horsepower, the
 * bonus-malus class - is Motor Insurers' Bureau registry data that no single
 * insurer would hold about a stranger's car. Every one of these calculators
 * appears to be a thin proxy over one shared Bureau system, which is worth
 * remembering before this file has six more siblings: the right long-term
 * integration is probably with the Bureau, not with each insurer in turn.
 *
 * Note the response is 201 Created, not 200, and that a failure is reported
 * as a non-zero `errorCode` *inside* a successful-looking envelope rather
 * than as an HTTP error status.
 */
class ArmeniaInsuranceProvider implements InsuranceQuoteProviderInterface
{
    public const ORGANIZATION_SLUG = 'armenia-insurance';

    private const ENDPOINT = 'https://armeniainsurance.am/api/aswa';

    /**
     * Their own site sends this from a same-origin page and the API echoes
     * it back in access-control-allow-origin. Sent for the same reason the
     * scrapers send a browser User-Agent: to look like the caller the
     * endpoint expects rather than to defeat anything.
     */
    private const ORIGIN = 'https://armeniainsurance.am';

    /**
     * Private use. Their form also offers taxi and commercial types, which
     * the intake form does not ask about - everything quoted here is a
     * privately owned vehicle, matching the 'individual' owner_type the
     * controller fixes.
     */
    private const USE_TYPE = 'OWN';

    private const SUCCESS_CODE = '0';

    private const DATE_FORMAT = 'Ymd';

    public function __construct(private readonly InsuranceHttpClient $http) {}

    public function quote(AutoInsuranceRequest $request, QuoteIdentity $identity, Organization $partner): array
    {
        // Unlike INGO, this one wants explicit dates rather than a term.
        //
        // The honest answer for startDate is the Bureau's own
        // suggestedStartDate - a policy cannot begin while the current one
        // still runs, and INGO's FAQ notes a 10% surcharge when more than 80
        // days separate signing from the start. We have no un-borrowed way to
        // read that date yet (only Sil's calculator returns it, and that
        // route needs a bank account number - see MarketQuoteDetails), so
        // this quotes from tomorrow and the figure is indicative. Revisit
        // once there is a Bureau integration.
        $startDate = now()->addDay();
        $endDate = $startDate->copy()->addMonths($request->contract_term_months)->subDay();

        [$status, $body] = $this->http->json('POST', self::ENDPOINT, [
            'json' => [
                'plateNumber' => $identity->plateNumber,
                'idNumber' => $identity->idNumber,
                'startDate' => $startDate->format(self::DATE_FORMAT),
                'endDate' => $endDate->format(self::DATE_FORMAT),
                'useType' => self::USE_TYPE,
            ],
            'headers' => [
                'Accept' => 'application/json',
                'Origin' => self::ORIGIN,
            ],
        ]);

        $data = is_array($body) ? ($body['responseData'] ?? null) : null;
        $errorCode = is_array($data) ? ($data['errorCode'] ?? null) : null;

        if ($errorCode !== null && (string) $errorCode !== self::SUCCESS_CODE) {
            $message = is_string($data['errorText'] ?? null) ? $data['errorText'] : null;

            // Only a bad plate/ID pair blocks the whole request - every
            // insurer would reject that identically. Anything else this
            // insurer complains about ("BM class of insured is required" and
            // the like) is its own inability to price, so it declines and the
            // other insurers still answer. See InsuranceErrorClassifier.
            if (InsuranceErrorClassifier::isInvalidIdentity($message, (string) $errorCode)) {
                throw new InsuranceQuoteInputException($message);
            }

            return InsuranceQuoteResult::declined($partner, self::ORGANIZATION_SLUG, $status);
        }

        $premium = is_array($data) ? ($data['premium'] ?? null) : null;

        return InsuranceQuoteResult::from($premium, $request, $partner, self::ORGANIZATION_SLUG, $status);
    }
}
