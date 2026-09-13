<?php

namespace App\Services\Insurance;

use JsonSerializable;

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
