<?php

namespace App\Support;

use Illuminate\Http\Request;

class SafeRedirectUrl
{
    /**
     * Resolves an untrusted "send me back here" URL to something safe to
     * redirect to: the candidate if it points at this host, the fallback
     * otherwise.
     *
     * Candidates come from places the visitor's browser controls - a
     * return_to form field, or the Referer header - and Laravel's
     * redirect()->to() will happily send someone to another origin. That
     * makes an unchecked candidate an open redirect: a link that starts on
     * this domain and silently lands somewhere else, which is exactly what
     * a phishing page wants to borrow.
     *
     * A relative path is accepted as-is (no host to compare, and it cannot
     * leave the site). Anything with a host must match the current one -
     * "starts with our domain" is deliberately not the test, since
     * https://findex.am.evil.test passes that and is not us.
     */
    public static function resolve(Request $request, ?string $candidate, string $fallback): string
    {
        $candidate = trim((string) $candidate);

        if ($candidate === '') {
            return $fallback;
        }

        $host = parse_url($candidate, PHP_URL_HOST);

        // parse_url returns false on a malformed URL - not a host we can
        // vouch for, so it takes the fallback like anything else foreign.
        if ($host === false) {
            return $fallback;
        }

        if ($host === null) {
            // No host means either a site-relative path or a scheme with
            // nothing host-like after it - javascript:, data:, mailto:. Only
            // the first is a redirect target, so this requires a leading
            // single slash rather than merely rejecting the schemes known
            // to be dangerous today. "//evil.test/x" is protocol-relative
            // and does leave the site, hence the second check.
            return str_starts_with($candidate, '/') && ! str_starts_with($candidate, '//')
                ? $candidate
                : $fallback;
        }

        return $host === $request->getHost() ? $candidate : $fallback;
    }
}
