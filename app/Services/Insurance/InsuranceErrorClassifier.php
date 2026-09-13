<?php

namespace App\Services\Insurance;

final class InsuranceErrorClassifier
{
    // Signals that the plate/ID pair itself is wrong.
    private const IDENTITY_SIGNALS = [
        'err_033',                         // INGO's internal code for the mismatch
        'does not match', "doesn't match", 'mismatch',
        'is not valid', 'not a valid',
        'vehicle not found', 'vehicle_not_found',
        'person not found', 'person_not_found', 'personnotfound',
        'wrong plate', 'wrong_plate',
        // Armenian / Russian equivalents seen on these registries.
        'չեն համընկնում',                  // "do not match"
        'չի գտնվել',                       // "not found"
        'не совпада',                      // "do not match" (stem)
        'не найден',                       // "not found" (stem)
    ];

    public static function isInvalidIdentity(?string ...$parts): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter($parts, static fn ($p) => $p !== null && $p !== '')));

        if ($haystack === '') {
            return false;
        }

        foreach (self::IDENTITY_SIGNALS as $signal) {
            if (str_contains($haystack, $signal)) {
                return true;
            }
        }

        return false;
    }
}
