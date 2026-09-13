<?php

namespace App\Services\Insurance;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

// The HTTP side of asking an insurer for a premium, shared by every provider.
class InsuranceHttpClient
{
    private const TIMEOUT_SECONDS = 15;

    private const CONNECT_TIMEOUT_SECONDS = 5;

    // Kept low on purpose.
    private const MAX_RETRIES = 2;

    public const STATUS_NO_RESPONSE = 0;

    /**
     * A small pool of current, real desktop browser User-Agents.
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
            // The exception is dropped rather than inspected on purpose - see the class docblock.
            return [self::STATUS_NO_RESPONSE, null];
        }

        return [
            $response->getStatusCode(),
            json_decode((string) $response->getBody(), true),
        ];
    }

    /**
     * Headers a real browser sends, with a rotating User-Agent.
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

        // A network-level failure (timeout, refused, DNS) has no response - worth one more try.
        if ($exception !== null) {
            return true;
        }

        $status = $response?->getStatusCode();

        // 429 (rate limited) and 5xx (their side wobbled) only.
        return $status === 429 || ($status !== null && $status >= 500);
    }

    private static function retryDelay(int $retries, ?ResponseInterface $response = null): int
    {
        $retryAfter = $response?->getHeaderLine('Retry-After');

        if ($retryAfter !== null && is_numeric($retryAfter)) {
            return (int) $retryAfter * 1000;
        }

        // Otherwise 1s, then 2s. Guzzle passes a 1-based count here.
        return 1000 * $retries;
    }
}
