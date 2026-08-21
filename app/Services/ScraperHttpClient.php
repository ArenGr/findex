<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The HTTP side of scraping, shared by everything that fetches a bank's page.
 *
 * Extracted from RateScraper, which owned the only copy that checked the
 * SSRF guard on every hop. Keeping one copy means a header fix or a retry
 * change lands everywhere at once, rather than in whichever scraper someone
 * happened to be editing.
 */
class ScraperHttpClient
{
    /**
     * Retries for transient failures only (connection/timeout errors, 5xx,
     * 429) - a plain 403/404 means the site is actively blocking us or the
     * URL is wrong, and hammering it again won't help. Kept short since
     * this runs in a daily cron job for many organizations in sequence, not
     * as a background retry queue.
     */
    private const MAX_RETRIES = 2;

    private Client $httpClient;

    public function __construct(private OutboundUrlGuard $urlGuard)
    {
        $handlerStack = HandlerStack::create();
        $handlerStack->push(Middleware::retry(
            self::shouldRetry(...),
            self::retryDelay(...),
        ));

        $this->httpClient = new Client([
            'handler' => $handlerStack,
            'timeout' => 20,
            'allow_redirects' => [
                'max' => 5,
                // Every hop is re-checked, not just the URL we set out with.
                // The destination of a redirect is chosen by whichever site we
                // just asked, so a bank whose page is compromised could
                // otherwise walk our server into the private network.
                'on_redirect' => function ($request, $response, $uri) {
                    $this->urlGuard->assertAllowed((string) $uri);
                },
            ],
            // Some sites (e.g. Ameriabank) gate the first request behind a
            // WAF challenge that sets a cookie and redirects to the same
            // URL; the retry only succeeds if that cookie is sent back.
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                // A bare language tag, not the browser-style
                // 'en-US,en;q=0.9'. Converse's API reads this header as a
                // locale *selector* rather than a preference list and
                // rejects anything with a region or a q-value - answering
                // 200 with the body "Invalid local en-US,en;q=0.9", so the
                // scrape books a success and stores nothing. Verified
                // byte-identical responses from the other banks either way;
                // the only differences were per-request session and CSRF
                // tokens.
                'Accept-Language' => 'en',
                // Only advertise encodings Guzzle/cURL can transparently decode.
                'Accept-Encoding' => 'gzip, deflate',
                'Referer' => 'https://www.google.com/',
                'Upgrade-Insecure-Requests' => '1',
            ],
        ]);
    }

    /**
     * Fetch a URL's body. Always live - no caching, so this always reflects
     * whatever the bank is currently publishing.
     */
    /**
     * @param  array<string, string>  $headers  per-source overrides, merged
     *                                          over the defaults above
     */
    public function get(string $url, array $headers = []): string
    {
        $this->urlGuard->assertAllowed($url);

        $options = $headers === [] ? [] : ['headers' => $headers];

        return (string) $this->httpClient->get($url, $options)->getBody();
    }

    private static function shouldRetry(
        int $retries,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?\Throwable $exception = null,
    ): bool {
        if ($retries >= self::MAX_RETRIES) {
            return false;
        }

        // A network-level failure (DNS, connection refused, timeout, ...)
        // has no response at all - always worth a retry.
        if ($exception !== null) {
            return true;
        }

        $status = $response?->getStatusCode();

        return $status !== null && ($status >= 500 || $status === 429);
    }

    private static function retryDelay(int $retries): int
    {
        // Guzzle passes a 1-based retry count here (1, 2, ...), unlike the
        // 0-based count shouldRetry() sees. Milliseconds: 1s, then 3s.
        return (int) (1000 * (2 * ($retries - 1) + 1));
    }
}
