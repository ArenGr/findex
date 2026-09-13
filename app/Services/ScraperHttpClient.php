<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// The HTTP side of scraping, shared by everything that fetches a bank's page.
class ScraperHttpClient
{
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
                'on_redirect' => function ($request, $response, $uri) {
                    $this->urlGuard->assertAllowed((string) $uri);
                },
            ],
            // Some sites (e.g.
            'cookies' => true,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                // A bare language tag, not the browser-style 'en-US,en;q=0.9'.
                'Accept-Language' => 'en',
                // Only advertise encodings Guzzle/cURL can transparently decode.
                'Accept-Encoding' => 'gzip, deflate',
                'Referer' => 'https://www.google.com/',
                'Upgrade-Insecure-Requests' => '1',
            ],
        ]);
    }

    // Fetch a URL's body.
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

        if ($exception !== null) {
            return true;
        }

        $status = $response?->getStatusCode();

        return $status !== null && ($status >= 500 || $status === 429);
    }

    private static function retryDelay(int $retries): int
    {
        return (int) (1000 * (2 * ($retries - 1) + 1));
    }
}
