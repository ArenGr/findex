<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Baseline response headers for every web request.
     *
     * No Content-Security-Policy yet, deliberately: the app inlines Alpine
     * expressions in x-data/@click attributes throughout, which a useful CSP
     * would have to allow via 'unsafe-eval' - so a CSP added today would be
     * mostly theatre. The headers below are the ones that work without
     * restructuring the frontend first.
     *
     * HSTS is only sent over HTTPS - emitting it on a plaintext response is
     * ignored by browsers per the spec, and sending it in local dev (which
     * runs on http://127.0.0.1) would pin localhost to HTTPS in the
     * developer's browser and break every other local project on that host.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Clickjacking: the org/admin dashboards must not be framable.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Stops the browser MIME-sniffing a response into something
        // executable (e.g. a stored upload served as text/plain).
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Send the origin but not the path cross-origin, so the signed
        // results URLs (which are bearer credentials) can't leak in a
        // Referer header to an outbound link's destination.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
