<?php

namespace Tests\Feature;

use App\Models\AutoInsuranceRequest;
use App\Services\Insurance\InsuranceHttpClient;
use App\Services\Insurance\InsuranceQuoteInputException;
use App\Services\Insurance\MarketQuoteDetails;
use App\Services\Insurance\QuoteIdentity;
use App\Services\Insurance\SilMarketQuoteSource;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

// Sil's calculator asks the Motor Insurers' Bureau for the whole market and renders one row of it.
class SilMarketQuoteSourceTest extends TestCase
{
    private const DRAFT = '{"draft":{"token":"64f529dc-6237-42b3-b402-bb51a5ff660d","suggestedStartDate":1817495940000,"vehicleMark":"JEEP","horsePower":177},"bonus":9}';

    private const PREMIUMS = '{"contract":null,"premium":[{"premium":39000,"icId":1},{"premium":44000,"icId":2},{"premium":47000,"icId":3},{"premium":42000,"icId":4},{"premium":40000,"icId":5},{"premium":44000,"icId":6}]}';

    /**
     * @param  array<int, mixed>  $queue
     */
    private function source(array $queue): SilMarketQuoteSource
    {
        return new SilMarketQuoteSource(new InsuranceHttpClient(new Client([
            'handler' => HandlerStack::create(new MockHandler($queue)),
            'http_errors' => false,
        ])));
    }

    private function premiums(array $queue): array
    {
        return $this->source($queue)->premiums(
            new AutoInsuranceRequest(['locale' => 'en', 'contract_term_months' => 12]),
            new QuoteIdentity('01AA123', 'AN1234567'),
            new MarketQuoteDetails('+37400000000', 'a@example.com', '1234567890123456'),
        );
    }

    public function test_it_maps_every_row_to_its_insurer(): void
    {
        $premiums = $this->premiums([
            new Response(200, [], self::DRAFT),
            new Response(200, [], self::PREMIUMS),
        ]);

        // All six Bureau rows are now identified (see INSURER_IDS for how each was pinned).
        $this->assertSame([
            'liga-insurance' => '39000.00',
            'armenia-insurance' => '44000.00',
            'nairi-insurance' => '47000.00',
            'sil-insurance' => '42000.00',
            'ingo-armenia' => '40000.00',
            'rego-insurance' => '44000.00',
        ], $premiums);
    }

    public function test_an_unknown_row_is_dropped_rather_than_guessed(): void
    {
        $premiums = $this->premiums([
            new Response(200, [], self::DRAFT),
            new Response(200, [], '{"contract":null,"premium":[{"premium":40000,"icId":5},{"premium":50000,"icId":99}]}'),
        ]);

        $this->assertSame(['ingo-armenia' => '40000.00'], $premiums);
    }

    public function test_a_failed_first_step_yields_nothing_rather_than_erroring(): void
    {
        // Without a token there is no premium call to make.
        $this->assertSame([], $this->premiums([
            new Response(200, [], '{"error":"not found"}'),
        ]));
    }

    public function test_a_rejection_arrives_as_a_populated_contract_object(): void
    {
        $this->assertSame([], $this->premiums([
            new Response(200, [], self::DRAFT),
            new Response(200, [], '{"contract":{"code":"E12","developerMessage":"no such vehicle"}}'),
        ]));
    }

    public function test_a_client_side_contract_error_surfaces_to_the_user(): void
    {
        $this->expectException(InsuranceQuoteInputException::class);
        $this->expectExceptionMessage('Invalid mail address');

        $this->premiums([
            new Response(200, [], self::DRAFT),
            new Response(200, [], '{"contract":{"code":40002,"message":"Invalid mail address"},"premium":{"code":50000}}'),
        ]);
    }

    public function test_a_server_side_contract_error_yields_no_quotes_without_blocking(): void
    {
        $this->assertSame([], $this->premiums([
            new Response(200, [], self::DRAFT),
            new Response(200, [], '{"contract":{"code":50000,"message":"Internal Server Error"},"premium":{"code":50000}}'),
        ]));
    }

    public function test_a_refused_plate_or_id_at_the_draft_step_surfaces_to_the_user(): void
    {
        $this->expectException(InsuranceQuoteInputException::class);

        $this->premiums([
            new Response(200, [], '{"draft":null,"error":"Vehicle owner does not match given ID"}'),
        ]);
    }
}
