<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use Illuminate\Support\Facades\Log;

class SilMarketQuoteSource implements MarketQuoteSourceInterface
{
    private const ENDPOINT = 'https://silinsurance.am/draft.php';

    /**
     * Which insurer each row of the Bureau's premium table belongs to.
     *
     * @var array<int, string>
     */
    private const INSURER_IDS = [
        1 => 'liga-insurance',
        2 => ArmeniaInsuranceProvider::ORGANIZATION_SLUG,
        3 => 'nairi-insurance',
        4 => 'sil-insurance',
        5 => IngoAppaProvider::ORGANIZATION_SLUG,
        6 => 'rego-insurance',
    ];

    private const USE_TYPE_ID = 1;

    public function __construct(private readonly InsuranceHttpClient $http) {}

    /**
     * @return array<string, string> organization slug => premium amount
     */
    public function premiums(
        AutoInsuranceRequest $request,
        QuoteIdentity $identity,
        MarketQuoteDetails $details,
    ): array {
        $draft = $this->draft($identity, $request);

        if ($draft === null) {
            return [];
        }

        [$status, $body] = $this->http->json('POST', self::ENDPOINT, [
            'multipart' => self::multipart([
                'action' => 'contract',
                'aswaToken' => $draft['token'],
                'startDate' => $draft['suggestedStartDate'],
                'useTypeId' => self::USE_TYPE_ID,
                'durationInMonths' => $request->contract_term_months,
                'PHONE' => $details->phone,
                'EMAIL' => $details->email,
                'BANKACCNUMBER' => $details->bankAccountNumber,
            ]),
        ]);

        $contract = is_array($body) ? ($body['contract'] ?? null) : null;
        $code = is_array($contract) ? ($contract['code'] ?? null) : null;

        if ($code !== null) {
            $this->failContract($code, is_string($contract['message'] ?? null) ? $contract['message'] : null);
        }

        return $this->mapPremiums(is_array($body) ? ($body['premium'] ?? null) : null, $status);
    }

    /**
     * @return array{token: string, suggestedStartDate: int}|null
     */
    private function draft(QuoteIdentity $identity, AutoInsuranceRequest $request): ?array
    {
        [$status, $body] = $this->http->json('POST', self::ENDPOINT, [
            'multipart' => self::multipart([
                'PLATENUMBER' => $identity->plateNumber,
                'DOCUMENT' => $identity->idNumber,
                'useTypeId' => self::USE_TYPE_ID,
                'durationInMonths' => $request->contract_term_months,
            ]),
        ]);

        $token = is_array($body) ? ($body['draft']['token'] ?? null) : null;
        $startDate = is_array($body) ? ($body['draft']['suggestedStartDate'] ?? null) : null;

        if (is_string($token) && $token !== '' && is_numeric($startDate)) {
            return ['token' => $token, 'suggestedStartDate' => (int) $startDate];
        }

        // No token means the plate/ID was refused at the registry.
        $error = is_array($body) ? ($body['error'] ?? $body['message'] ?? null) : null;
        $message = is_string($error) ? $error : null;

        if ($message !== null && InsuranceErrorClassifier::isInvalidIdentity($message)) {
            throw new InsuranceQuoteInputException($message);
        }

        Log::warning('Market quote draft failed', ['source' => 'sil', 'status' => $status]);

        return null;
    }

    // Sil's premium step refused what the user supplied.
    private function failContract(int|string $code, ?string $message): void
    {
        if (is_numeric($code) && (int) $code < 50000 && $message !== null && $message !== '') {
            throw new InsuranceQuoteInputException($message);
        }

        Log::warning('Market quote rejected', ['source' => 'sil', 'code' => $code]);
    }

    /**
     * @param  mixed  $premiums
     * @return array<string, string>
     */
    private function mapPremiums($premiums, int $status): array
    {
        if (! is_array($premiums)) {
            Log::warning('Market quote returned no premiums', ['source' => 'sil', 'status' => $status]);

            return [];
        }

        $mapped = [];

        foreach ($premiums as $row) {
            $slug = self::INSURER_IDS[$row['icId'] ?? null] ?? null;
            $premium = $row['premium'] ?? null;

            if ($slug !== null && is_numeric($premium) && $premium > 0) {
                $mapped[$slug] = number_format((float) $premium, 2, '.', '');
            }
        }

        return $mapped;
    }

    /**
     * Their page submits a FormData, so this mirrors it as multipart rather than as a urlencoded body.
     *
     * @param  array<string, string|int>  $fields
     * @return array<int, array{name: string, contents: string}>
     */
    private static function multipart(array $fields): array
    {
        $parts = [];

        foreach ($fields as $name => $contents) {
            $parts[] = ['name' => $name, 'contents' => (string) $contents];
        }

        return $parts;
    }
}
