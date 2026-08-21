<?php

namespace App\Services\Insurance;

use App\Models\AutoInsuranceRequest;
use Illuminate\Support\Facades\Log;

/**
 * Premiums for several insurers at once, read from Sil Insurance's own
 * compulsory-motor-TPL calculator.
 *
 * Sil's calculator asks the Motor Insurers' Bureau for the whole market and
 * then displays exactly one row of it - their page literally does
 * `data.premium.find(item => item.icId === 4)`, where 4 is Sil. The other
 * rows are their competitors' premiums, returned and discarded. This reads
 * the same response and keeps them.
 *
 * Two calls, both to draft.php:
 *
 *   1. no action, PLATENUMBER + DOCUMENT -> {draft: {token, suggestedStartDate,
 *      vehicleMark, horsePower, ...}, bonus: N}   (registry data)
 *   2. action=contract + that token + PHONE, EMAIL, BANKACCNUMBER
 *      -> {contract: null, premium: [{premium, icId}, ...]}
 *
 * Step 2 is why this is opt-in and is not a plain price lookup: it wants a
 * bank account number, and `action=contract` creates a draft contract on
 * Sil's side. See MarketQuoteDetails for that decision. Step 1 alone needs
 * nothing but a plate and an ID, so if the suggested start date is ever
 * wanted for its own sake, it can be had without any of this.
 */
class SilMarketQuoteSource implements MarketQuoteSourceInterface
{
    private const ENDPOINT = 'https://silinsurance.am/draft.php';

    /**
     * Which insurer each row of the Bureau's premium table belongs to.
     *
     * Only entries verified against a second, independent source belong
     * here - a wrong slug would publish one insurer's premium under another
     * insurer's name, which is worse than showing nothing at all.
     *
     * The full table, each row confirmed:
     *   1 - Liga, whose own quote for the reference vehicle was 39000 - the
     *       only 39000 row, so unambiguous.
     *   2 - Armenia Insurance. Its own API returned 44000 on a 12-month
     *       contract (rows 2 and 6 were both 44000, so that alone did not
     *       decide it) and 15000 on a 3-month one, where row 2 was 15000 and
     *       row 6 was 14000 - which pins it to 2. (Row 3 was also 15000 at 3
     *       months, but that is Nairi, confirmed below.)
     *   3 - Nairi, whose own create-object endpoint returned premiumspay
     *       47000 for the vehicle this row priced at 47000.
     *   4 - Sil, hardcoded as its own row in their page's JavaScript.
     *   5 - INGO, whose own API returned exactly this row's premium for the
     *       same vehicle (see IngoAppaProvider::BUREAU_INSURER_ID).
     *   6 - REGO, by elimination: the other five ids are each independently
     *       confirmed, so the sixth row can only be the sixth insurer. REGO
     *       runs no open calculator of its own - its portal hands off to the
     *       Bureau at aswa.am - so this table is the only way to reach its
     *       premium at all, which is exactly why it had to be pinned this way.
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

    /**
     * Private use, matching their form's "personal" option (2 is taxi, 6 is
     * commercial) and the 'individual' owner_type the controller fixes.
     */
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

        // A rejection arrives as a populated `contract` object rather than an
        // HTTP error - their own page checks `data.contract?.code`.
        $contract = is_array($body) ? ($body['contract'] ?? null) : null;
        $code = is_array($contract) ? ($contract['code'] ?? null) : null;

        if ($code !== null) {
            $this->failContract($code, is_string($contract['message'] ?? null) ? $contract['message'] : null);
        }

        return $this->mapPremiums(is_array($body) ? ($body['premium'] ?? null) : null, $status);
    }

    /**
     * Step 1: exchange a plate and an ID for the registry's own view of the
     * vehicle - the token the premium call needs, and the date the next
     * policy may actually begin.
     *
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

        // No token means the plate/ID was refused at the registry. If Sil
        // named the reason and it points at the identifiers, surface it on
        // the form - every insurer would reject the same pair. Otherwise it
        // is a transient failure: no quotes, but nothing for the user to fix.
        $error = is_array($body) ? ($body['error'] ?? $body['message'] ?? null) : null;
        $message = is_string($error) ? $error : null;

        if ($message !== null && InsuranceErrorClassifier::isInvalidIdentity($message)) {
            throw new InsuranceQuoteInputException($message);
        }

        Log::warning('Market quote draft failed', ['source' => 'sil', 'status' => $status]);

        return null;
    }

    /**
     * Sil's premium step refused what the user supplied. Their `contract.code`
     * distinguishes a user-fixable problem (a malformed email, a bank account
     * whose code it does not recognise) from its own server error - the
     * former is worth putting back on the form, the latter is just "no
     * quotes". Codes below 50000 are client errors, 50000+ are theirs.
     */
    private function failContract(int|string $code, ?string $message): void
    {
        // Numeric codes below 50000 are Sil's client errors (bad email 40002,
        // unknown bank 40401); 50000+ is its own server error. A non-numeric
        // code is treated as a server-side hiccup too - not worth blocking
        // the user over something they cannot act on.
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

            // Rows whose insurer is not positively identified are dropped
            // rather than guessed at - see INSURER_IDS.
            if ($slug !== null && is_numeric($premium) && $premium > 0) {
                $mapped[$slug] = number_format((float) $premium, 2, '.', '');
            }
        }

        return $mapped;
    }

    /**
     * Their page submits a FormData, so this mirrors it as multipart rather
     * than as a urlencoded body.
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
