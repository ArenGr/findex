<?php

namespace App\Support;

class ValidationRules
{
    /**
     * DNS-checked (rejects a syntactically valid but nonexistent domain,
     * e.g. a typo) everywhere except under testing - the DNS check needs a
     * real network lookup, which the "@example.com" addresses used
     * throughout this test suite would always fail (example.com has no MX
     * record) and which CI may not even have outbound access for.
     */
    public static function email(): string
    {
        return app()->environment('testing') ? 'email' : 'email:rfc,dns';
    }
}
