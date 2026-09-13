<?php

namespace App\Support;

class ValidationRules
{
    // DNS-checked (rejects a syntactically valid but nonexistent domain, e.g.
    public static function email(): string
    {
        return app()->environment('testing') ? 'email' : 'email:rfc,dns';
    }
}
