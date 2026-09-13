<?php

namespace Tests\Feature;

use App\Services\Insurance\MarketQuoteDetails;
use App\Services\Insurance\QuoteIdentity;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class SensitiveQuoteValuesTest extends TestCase
{
    private const ID_NUMBER = 'AN1234567';

    private const BANK_ACCOUNT = '1234567890123456';

    public function test_an_identity_refuses_to_be_serialized_onto_a_queue(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must not be serialized');

        serialize(new QuoteIdentity('01AA123', self::ID_NUMBER));
    }

    public function test_market_details_refuse_to_be_serialized_too(): void
    {
        $this->expectException(LogicException::class);

        serialize(new MarketQuoteDetails('+37400000000', 'a@example.com', self::BANK_ACCOUNT));
    }

    public function test_encoding_an_identity_to_json_redacts_it(): void
    {
        $json = json_encode(new QuoteIdentity('01AA123', self::ID_NUMBER));

        $this->assertStringNotContainsString(self::ID_NUMBER, $json);
        $this->assertStringNotContainsString('01AA123', $json);
        $this->assertStringContainsString('[redacted]', $json);
    }

    public function test_encoding_market_details_to_json_redacts_the_bank_account(): void
    {
        $json = json_encode(new MarketQuoteDetails('+37400000000', 'a@example.com', self::BANK_ACCOUNT));

        $this->assertStringNotContainsString(self::BANK_ACCOUNT, $json);
    }

    public function test_dumping_an_identity_redacts_it(): void
    {
        $dumped = print_r(new QuoteIdentity('01AA123', self::ID_NUMBER), true);

        $this->assertStringNotContainsString(self::ID_NUMBER, $dumped);
    }

    public function test_interpolating_an_identity_into_a_string_redacts_it(): void
    {
        $identity = new QuoteIdentity('01AA123', self::ID_NUMBER);

        $this->assertStringNotContainsString(self::ID_NUMBER, "quote for {$identity}");
    }

    public function test_it_upper_cases_and_trims_the_plate_and_id(): void
    {
        $identity = new QuoteIdentity('  51ip551 ', ' ar0622937 ');

        $this->assertSame('51IP551', $identity->plateNumber);
        $this->assertSame('AR0622937', $identity->idNumber);
    }

    public function test_the_id_number_is_not_a_column_on_the_request(): void
    {
        $this->assertNotContains(
            'owner_id_number',
            Schema::getColumnListing('auto_insurance_requests'),
        );
    }
}
