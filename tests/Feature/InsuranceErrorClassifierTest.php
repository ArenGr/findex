<?php

namespace Tests\Feature;

use App\Services\Insurance\InsuranceErrorClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The rule that decides whether an insurer error blocks the whole request or
 * just drops that one insurer. Getting this wrong in the "block" direction is
 * the worse failure - it turns one insurer's quirk into a dead comparison -
 * so the emphasis is on what must NOT be treated as a user error.
 */
class InsuranceErrorClassifierTest extends TestCase
{
    public static function identityErrors(): array
    {
        return [
            'INGO mismatch text' => ['The provided ID number does not match the vehicle owner\'s ID number.'],
            'INGO code' => ['ERR_033'],
            'short mismatch' => ['Vehicle owner mismatch'],
            'vehicle not found' => ['Vehicle not found'],
            'person not found' => ['person_not_found'],
            'armenian mismatch' => ['Մեքենայի համարանիշը և փաստաթուղթը չեն համընկնում'],
        ];
    }

    #[DataProvider('identityErrors')]
    public function test_identity_errors_block(string $message): void
    {
        $this->assertTrue(InsuranceErrorClassifier::isInvalidIdentity($message));
    }

    public static function nonIdentityErrors(): array
    {
        return [
            'BM required' => ['BM class of insured is required'],
            'cannot receive BM' => ["Can't receive B/M"],
            'invalid bonus-malus' => ['InvalidBonusMalus'],
            'service down' => ['Service is temporarily unavailable'],
            'bank verification' => ['The verification of bank details failed. Please try again.'],
            'empty' => [''],
            'ordinary word' => ['premium calculated'],
        ];
    }

    #[DataProvider('nonIdentityErrors')]
    public function test_everything_else_declines(string $message): void
    {
        $this->assertFalse(InsuranceErrorClassifier::isInvalidIdentity($message));
    }

    public function test_it_reads_across_message_and_code_together(): void
    {
        $this->assertTrue(InsuranceErrorClassifier::isInvalidIdentity('Some opaque text', 'ERR_033'));
        $this->assertFalse(InsuranceErrorClassifier::isInvalidIdentity(null, '12'));
    }
}
