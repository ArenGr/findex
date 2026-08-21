<?php

namespace App\Services\Insurance;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The HTTP side of asking an insurer for a premium, shared by every provider.
 *
 * It exists to make one mistake impossible rather than merely discouraged.
 * These calls carry the vehicle owner's ID number, and two ordinary-looking
 * things would leak it:
 *
 *   1. Laravel's Http facade. Sentry's Laravel integration records every
 *      facade request as a breadcrumb and a trace span (config/sentry.php:
 *      `breadcrumbs.http_client_requests` and `tracing.http_client_requests`,
 *      both default true), and what it records is the full URL. INGO takes
 *      the ID number as a *query parameter*, so any unrelated exception later
 *      in the same request would carry that ID off to a third party, with
 *      nothing at the call site to suggest it. Plain Guzzle is not
 *      instrumented, so that cannot happen here.
 *
 *   2. Guzzle's own exceptions. A BadResponseException embeds the request URI
 *      in its message, and a ConnectException's message ends in "... for
 *      https://host/path?idNumber=...". Rethrowing or logging either one is
 *      the same leak by another route. So http_errors is off, every throwable
 *      is caught here, and callers get a status code and a decoded body -
 *      never an exception object that knows the URL.
 *
 * Being a considerate caller. These are public price calculators and we make
 * modest volume, but a naive script still reads as a naive script. So this
 *   - presents a current, real browser User-Agent, varied across a small
 *     pool per request, with the Accept headers a browser actually sends;
 *   - retries a rate-limit (429) or a transient 5xx with backoff, honouring
 *     Retry-After when the server sends it.
 * That is where "considerate" ends. It is NOT ban-evasion machinery: if a
 * site actively blocks us, the answer is to back off and lean on the other
 * source for that insurer (see AutoInsuranceQuoteService), not to disguise
 * ourselves and keep hammering. The UA pool is to look like the browser we
 * effectively are, not to impersonate many people to get around a block.
 */
class InsuranceHttpClient
{
    /**
     * Armenia Insurance answered in 3.5s for us and reported
     * `x-envoy-upstream-service-time: 9252` on a browser request - these
     * endpoints do a live registry lookup and are genuinely slow. This runs
     * inside the user's own page submit, so the ceiling is what someone will
     * wait for, but it has to clear the slow case or the quote fails for a
     * reason that looks like the insurer being down.
     */
    private const TIMEOUT_SECONDS = 15;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /**
     * Kept low on purpose. A retry is for a transient hiccup (429, 5xx),
     * not for grinding against a block - a 403 is not retried at all.
     */
    private const MAX_RETRIES = 2;

    /**
     * Status used when the request never produced a response at all - a
     * timeout, a DNS failure, a refused connection. Distinct from any real
     * HTTP status so a provider can tell "no answer" from "answered badly".
     */
    public const STATUS_NO_RESPONSE = 0;

    /**
     * A small pool of current, real desktop browser User-Agents. One is
     * chosen per request so our traffic is not a single identical fingerprint
     * repeated - the ordinary shape of real visitors, nothing more.
     *
     * @var list<string>
     */
    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:130.0) Gecko/20100101 Firefox/130.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
    ];

    private ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => self::TIMEOUT_SECONDS,
            'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
            'http_errors' => false,
            'handler' => $this->handlerStack(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $options  Guzzle request options
     * @return array{0: int, 1: mixed} HTTP status (or STATUS_NO_RESPONSE) and
     *                                 the decoded JSON body, null if the body
     *                                 was not JSON
     */
    public function json(string $method, string $url, array $options): array
    {
        $options['headers'] = array_merge($this->browserHeaders(), $options['headers'] ?? []);

        try {
            $response = $this->client->request($method, $url, $options);
        } catch (Throwable) {
            // The exception is dropped rather than inspected on purpose - see
            // the class docblock. Its message knows the URL, and the URL may
            // know the ID number.
            return [self::STATUS_NO_RESPONSE, null];
        }

        return [
            $response->getStatusCode(),
            json_decode((string) $response->getBody(), true),
        ];
    }

    /**
     * Headers a real browser sends, with a rotating User-Agent. Per-request
     * so each call picks its own; callers can still override any of these.
     *
     * @return array<string, string>
     */
    private function browserHeaders(): array
    {
        return [
            'User-Agent' => self::USER_AGENTS[array_rand(self::USER_AGENTS)],
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US,en;q=0.9,hy;q=0.8,ru;q=0.7',
            'Accept-Encoding' => 'gzip, deflate',
        ];
    }

    private function handlerStack(): HandlerStack
    {
        $stack = HandlerStack::create();
        $stack->push(Middleware::retry(self::shouldRetry(...), self::retryDelay(...)));

        return $stack;
    }

    private static function shouldRetry(
        int $retries,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?Throwable $exception = null,
    ): bool {
        if ($retries >= self::MAX_RETRIES) {
            return false;
        }

        // A network-level failure (timeout, refused, DNS) has no response -
        // worth one more try.
        if ($exception !== null) {
            return true;
        }

        $status = $response?->getStatusCode();

        // 429 (rate limited) and 5xx (their side wobbled) only. A 403/404 is
        // a deliberate refusal or a wrong URL - retrying just annoys them.
        return $status === 429 || ($status !== null && $status >= 500);
    }

    private static function retryDelay(int $retries, ?ResponseInterface $response = null): int
    {
        // Honour Retry-After when the server names a delay - overshooting a
        // rate limit is how a soft throttle becomes a hard block.
        $retryAfter = $response?->getHeaderLine('Retry-After');

        if ($retryAfter !== null && is_numeric($retryAfter)) {
            return (int) $retryAfter * 1000;
        }

        // Otherwise 1s, then 2s. Guzzle passes a 1-based count here.
        return 1000 * $retries;
    }
}
