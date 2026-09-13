<?php

namespace App\Services\Insurance;

use JsonSerializable;

// The extra details some insurers demand before they will quote at all.
final class MarketQuoteDetails implements JsonSerializable
{
    use RedactsSensitiveValues;

    public function __construct(
        #[\SensitiveParameter]
        public readonly string $phone,
        #[\SensitiveParameter]
        public readonly string $email,
        #[\SensitiveParameter]
        public readonly string $bankAccountNumber,
    ) {}
}
