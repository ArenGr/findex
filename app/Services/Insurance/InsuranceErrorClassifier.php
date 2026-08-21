<?php

namespace App\Services\Insurance;

/**
 * Decides whether an insurer's error is the user's to fix or the insurer's
 * own problem - the distinction that keeps one insurer's quirk from taking
 * the whole comparison down.
 *
 * Only a genuinely bad plate/ID pair should stop the request and show a form
 * error, because every insurer reads the same Motor Insurers' Bureau registry
 * and would reject it identically (see InsuranceQuoteInputException). Anything
 * else - "BM class of insured is required", "service unavailable", a missing
 * parameter one insurer wants and another does not - is that insurer failing
 * to price, and must degrade to a decline so the others still answer.
 *
 * The safe default is therefore to NOT block. This returns true only for
 * errors that clearly name the identifiers as the problem; everything it does
 * not recognise is treated as a per-insurer decline. Matching localised
 * message text is inherently imperfect, but the cost of a miss is only a less
 * precise message (declines instead of a pinpointed field error), never a
 * blocked request - so uncertainty always resolves towards letting the
 * comparison proceed.
 */
final class InsuranceErrorClassifier
{
    /**
     * Signals that the plate/ID pair itself is wrong. Lower-cased substrings,
     * matched against the insurer's own message and error code across the
     * languages these APIs answer in. Kept tight and unambiguous so ordinary
     * wording ("premium", "contract") never trips them.
     *
     * Notably ABSENT: anything about bonus-malus. "BM class required",
     * "can't receive B/M", "invalid bonus-malus" are the insurer unable to
     * derive a rating factor - a decline, not a user identity error.
     */
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
