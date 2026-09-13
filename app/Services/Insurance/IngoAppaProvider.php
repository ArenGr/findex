<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

// Live compulsory motor TPL (ԱՊՊԱ) premiums from INGO Armenia.
class IngoAppaProvider implements InsuranceQuoteProviderInterface
{
    public const ORGANIZATION_SLUG = 'ingo-armenia';

    public const BUREAU_INSURER_ID = 5;

    private const ENDPOINT = 'https://ingoarmenia.am/api/appa/price';

    // Their API reads Language as a locale selector and answers with error text in that language.
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

            if (is_string($message) && $message !== '' && InsuranceErrorClassifier::isInvalidIdentity($message, is_string($code) ? $code : null)) {
                throw new InsuranceQuoteInputException($message);
            }
        }

        $price = ($status === 200 && is_array($body)) ? ($body['price'] ?? null) : null;

        return InsuranceQuoteResult::from($price, $request, $partner, self::ORGANIZATION_SLUG, $status);
    }
}
