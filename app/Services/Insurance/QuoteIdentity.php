<?php

namespace App\Services\Insurance;

use JsonSerializable;

/**
 * The two identifiers an insurer needs to price compulsory motor TPL: the
 * vehicle's plate and the owner's passport/ID/PSC number.
 *
 * These are deliberately *not* columns on AutoInsuranceRequest. Insurers
 * resolve the vehicle and the owner's bonus-malus class out of the Motor
 * Insurers' Bureau registry themselves, so the pair is only ever an input to
 * that lookup - once a premium comes back, nothing here reads them again.
 * Storing them would be retention with no purpose, and an owner's ID number
 * is not a thing to keep lying around waiting for a database to leak.
 *
 * So they live in this object, for the length of one HTTP request, and go no
 * further. See RedactsSensitiveValues for how that is enforced.
 *
 * Both values are upper-cased and trimmed on the way in. The Bureau (and Sil's
 * calculator in front of it) reject a lower-cased plate outright with "Wrong
 * plate number" - their own form quietly upper-cases the field as you type, so
 * this makes the canonical form the only form that exists here.
 */
final class QuoteIdentity implements JsonSerializable
{
    use RedactsSensitiveValues;

    public readonly string $plateNumber;

    public readonly string $idNumber;

    public function __construct(
        #[\SensitiveParameter]
        string $plateNumber,
        #[\SensitiveParameter]
        string $idNumber,
    ) {
        $this->plateNumber = mb_strtoupper(trim($plateNumber));
        $this->idNumber = mb_strtoupper(trim($idNumber));
    }
}
