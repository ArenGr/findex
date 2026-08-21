<?php

namespace Tests\Feature;

use App\Services\Insurance\MarketQuoteDetails;
use App\Services\Insurance\QuoteIdentity;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * The vehicle owner's ID number and the bank account number Sil's calculator
 * demands are the two values this application handles but never stores.
 *
 * Nothing in the code has to remember that - these tests hold the carriers to
 * it. They exist because the failure they guard against is silent: a value
 * that leaks into a log line, a Sentry payload or the jobs table does not
 * break anything, it just quietly starts being kept.
 */
class SensitiveQuoteValuesTest extends TestCase
{
    private const ID_NUMBER = 'AN1234567';

    private const BANK_ACCOUNT = '1234567890123456';

    public function test_an_identity_refuses_to_be_serialized_onto_a_queue(): void
    {
        // serialize() is how a queued job's payload is built, and that
        // payload is written to the jobs table in plain text. Throwing means
        // "let's do this in the background" fails while someone is writing
        // it, rather than starting to log ID numbers to the database.
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
        // print_r/var_dump go through __debugInfo - this is the path a
        // dd() left in by accident, or a framework error page, would take.
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
        // The Bureau rejects a lower-cased plate with "Wrong plate number",
        // so the canonical upper-case form is the only form stored here.
        $identity = new QuoteIdentity('  51ip551 ', ' ar0622937 ');

        $this->assertSame('51IP551', $identity->plateNumber);
        $this->assertSame('AR0622937', $identity->idNumber);
    }

    public function test_the_id_number_is_not_a_column_on_the_request(): void
    {
        // The whole arrangement above is pointless if the value is also
        // sitting in a table, so this asserts the column is gone rather than
        // merely unused.
        $this->assertNotContains(
            'owner_id_number',
            Schema::getColumnListing('auto_insurance_requests'),
        );
    }
}
