<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use App\Models\Organization;

// Live compulsory motor TPL premiums from Armenia Insurance.
class ArmeniaInsuranceProvider implements InsuranceQuoteProviderInterface
{
    public const ORGANIZATION_SLUG = 'armenia-insurance';

    private const ENDPOINT = 'https://armeniainsurance.am/api/aswa';

    private const ORIGIN = 'https://armeniainsurance.am';

    // Private use.
    private const USE_TYPE = 'OWN';

    private const SUCCESS_CODE = '0';

    private const DATE_FORMAT = 'Ymd';

    public function __construct(private readonly InsuranceHttpClient $http) {}

    public function quote(AutoInsuranceRequest $request, QuoteIdentity $identity, Organization $partner): array
    {
        // Unlike INGO, this one wants explicit dates rather than a term.
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

            // Only a bad plate/ID pair blocks the whole request - every insurer would reject that identically.
            if (InsuranceErrorClassifier::isInvalidIdentity($message, (string) $errorCode)) {
                throw new InsuranceQuoteInputException($message);
            }

            return InsuranceQuoteResult::declined($partner, self::ORGANIZATION_SLUG, $status);
        }

        $premium = is_array($data) ? ($data['premium'] ?? null) : null;

        return InsuranceQuoteResult::from($premium, $request, $partner, self::ORGANIZATION_SLUG, $status);
    }
}
