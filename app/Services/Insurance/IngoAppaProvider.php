<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

/**
 * Live compulsory motor TPL (ԱՊՊԱ) premiums from INGO Armenia.
 *
 * Their site is a React app talking to its own JSON API, so this asks that
 * API the same question the site's own calculator asks - the approach the
 * rate parsers already take with the banks (read the endpoint the page's
 * script calls, not the rendered markup).
 *
 *     GET /api/appa/price?plateNumber=&idNumber=&duration=
 *     -> {"price":40000,"insurancePrice":40000,"risks":[]}
 *
 * Three inputs, and none of them are rating factors: INGO resolves the
 * vehicle and the owner's bonus-malus class out of the Motor Insurers'
 * Bureau registry itself. That is why the request form asks for a plate and
 * an ID rather than engine size and years of driving, and why an ID that
 * isn't the registered owner's is rejected outright rather than priced.
 *
 * It takes no start date, only a term, so unlike ArmeniaInsuranceProvider it
 * needs no view on when the policy would actually begin - INGO resolves that
 * from the registry too.
 */
class IngoAppaProvider implements InsuranceQuoteProviderInterface
{
    public const ORGANIZATION_SLUG = 'ingo-armenia';

    /**
     * The insurer's row in the Motor Insurers' Bureau premium table that
     * Sil's calculator returns - see SilMarketQuoteSource. Verified: the
     * table's icId 5 was 40,000 for the same vehicle this provider
     * independently quoted at 40,000.
     */
    public const BUREAU_INSURER_ID = 5;

    private const ENDPOINT = 'https://ingoarmenia.am/api/appa/price';

    /**
     * Their API reads Language as a locale selector and answers with
     * error text in that language. Anything outside this set gets English.
     */
    private const LANGUAGES = ['hy' => 'HY', 'ru' => 'RU', 'en' => 'EN'];

    private const DEFAULT_LANGUAGE = 'EN';

    public function __construct(private readonly InsuranceHttpClient $http) {}

    public function quote(AutoInsuranceRequest $request, QuoteIdentity $identity, Organization $partner): array
    {
        [$status, $body] = $this->http->json('GET', self::ENDPOINT, [
            'query' => [
                'plateNumber' => $identity->plateNumber,
                'idNumber' => $identity->idNumber,
                'duration' => $request->contract_term_months,
            ],
            'headers' => [
                'Accept' => 'application/json',
                // Their API requires both; it answers 400 without them.
                'Source' => 'WEB',
                'Language' => self::LANGUAGES[$request->locale] ?? self::DEFAULT_LANGUAGE,
            ],
        ]);

        if ($status >= 400) {
            $error = is_array($body) ? ($body['errors'][0] ?? null) : null;
            $message = is_array($error) ? ($error['message'] ?? null) : null;
            $code = is_array($error) ? ($error['internalCode'] ?? null) : null;

            // A bad plate/ID pair is the one error worth blocking the whole
            // request over, since every insurer rejects it the same way.
            // Any other 400 (a missing rating factor, a bad day on their
            // side) declines instead, so the rest of the comparison stands.
            // See InsuranceErrorClassifier.
            if (is_string($message) && $message !== '' && InsuranceErrorClassifier::isInvalidIdentity($message, is_string($code) ? $code : null)) {
                throw new InsuranceQuoteInputException($message);
            }
        }

        // `insurancePrice` was observed equal to `price` on a live quote; the
        // site's own calculator renders `price`, and derives the higher
        // walk-in figure from it rather than reading a second field.
        $price = ($status === 200 && is_array($body)) ? ($body['price'] ?? null) : null;

        return InsuranceQuoteResult::from($price, $request, $partner, self::ORGANIZATION_SLUG, $status);
    }
}
