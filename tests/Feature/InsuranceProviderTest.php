<?php

namespace Tests\Feature;

use App\Models\AutoInsuranceQuote;
use App\Models\AutoInsuranceRequest;
use App\Models\Organization;
use App\Services\Insurance\ArmeniaInsuranceProvider;
use App\Services\Insurance\IngoAppaProvider;
use App\Services\Insurance\InsuranceHttpClient;
use App\Services\Insurance\InsuranceQuoteInputException;
use App\Services\Insurance\QuoteIdentity;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

/**
 * The two live insurer integrations, driven off recorded responses.
 *
 * Every fixture here is a real response body observed against the live API,
 * with the plate and ID that produced it left out - the point of the flow is
 * that those never get written down, and a test fixture is written down.
 */
class InsuranceProviderTest extends TestCase
{
    private function request(int $termMonths = 12, string $locale = 'en'): AutoInsuranceRequest
    {
        return new AutoInsuranceRequest([
            'locale' => $locale,
            'contract_term_months' => $termMonths,
        ]);
    }

    private function partner(): Organization
    {
        return new Organization(['name' => 'Test Insurer', 'slug' => 'test-insurer']);
    }

    private function identity(): QuoteIdentity
    {
        return new QuoteIdentity('01AA123', 'AN1234567');
    }

    /**
     * @param  array<int, mixed>  $queue
     */
    private function http(array $queue): InsuranceHttpClient
    {
        return new InsuranceHttpClient(new Client([
            'handler' => HandlerStack::create(new MockHandler($queue)),
            'http_errors' => false,
        ]));
    }

    public function test_ingo_reads_the_premium_from_a_real_response_shape(): void
    {
        $http = $this->http([
            new Response(200, [], '{"price":40000,"insurancePrice":40000,"risks":[]}'),
        ]);

        $result = (new IngoAppaProvider($http))->quote($this->request(), $this->identity(), $this->partner());

        $this->assertSame(AutoInsuranceQuote::STATUS_QUOTED, $result['status']);
        $this->assertSame('40000.00', $result['premium_amount']);
        $this->assertSame('AMD', $result['premium_currency']);
        $this->assertSame(12, $result['policy_term_months']);
    }

    public function test_ingo_turns_a_rejected_id_into_an_input_exception(): void
    {
        // The live 400 body, verbatim.
        $http = $this->http([
            new Response(400, [], '{"errors":[{"message":"The provided ID number does not match the vehicle owner\'s ID number.","property":null}],"statusCode":400,"internalCode":"ERR_033"}'),
        ]);

        $this->expectException(InsuranceQuoteInputException::class);
        $this->expectExceptionMessage("does not match the vehicle owner's ID number");

        (new IngoAppaProvider($http))->quote($this->request(), $this->identity(), $this->partner());
    }

    public function test_a_dead_endpoint_declines_rather_than_throwing(): void
    {
        // One insurer being unreachable must not take the whole comparison
        // down with it - and the exception must not escape, because its
        // message would carry the URL and with it the ID number.
        $http = $this->http([
            new ConnectException('cURL error 28: timeout for https://ingoarmenia.am/api/appa/price?idNumber=AN1234567', new PsrRequest('GET', 'https://ingoarmenia.am')),
        ]);

        $result = (new IngoAppaProvider($http))->quote($this->request(), $this->identity(), $this->partner());

        $this->assertSame(AutoInsuranceQuote::STATUS_DECLINED, $result['status']);
        $this->assertNull($result['premium_amount']);
    }

    public function test_armenia_insurance_reads_a_premium_out_of_its_envelope(): void
    {
        // Note the 201, and the premium arriving as a string.
        $http = $this->http([
            new Response(201, [], '{"responseData":{"errorCode":"0","errorText":"Request completed successfully","premium":"44000"}}'),
        ]);

        $result = (new ArmeniaInsuranceProvider($http))->quote($this->request(), $this->identity(), $this->partner());

        $this->assertSame(AutoInsuranceQuote::STATUS_QUOTED, $result['status']);
        $this->assertSame('44000.00', $result['premium_amount']);
    }

    public function test_armenia_insurance_treats_a_non_zero_error_code_as_bad_input(): void
    {
        // The trap: a failure here arrives inside a 201, not as an HTTP
        // error, so a provider that only checked the status would store the
        // absence of a premium as a decline and hide a fixable typo.
        $http = $this->http([
            new Response(201, [], '{"responseData":{"errorCode":"33","errorText":"Vehicle owner mismatch","premium":null}}'),
        ]);

        $this->expectException(InsuranceQuoteInputException::class);
        $this->expectExceptionMessage('Vehicle owner mismatch');

        (new ArmeniaInsuranceProvider($http))->quote($this->request(), $this->identity(), $this->partner());
    }

    public function test_a_missing_rating_factor_declines_rather_than_blocking_the_whole_request(): void
    {
        // "BM class of insured is required" is this insurer unable to price,
        // not the user's ID being wrong - so it must NOT throw (which would
        // roll back every other insurer's quote too), it declines.
        $http = $this->http([
            new Response(201, [], '{"responseData":{"errorCode":"12","errorText":"BM class of insured is required","premium":null}}'),
        ]);

        $result = (new ArmeniaInsuranceProvider($http))->quote($this->request(), $this->identity(), $this->partner());

        $this->assertSame(AutoInsuranceQuote::STATUS_DECLINED, $result['status']);
        $this->assertNull($result['premium_amount']);
    }
}
