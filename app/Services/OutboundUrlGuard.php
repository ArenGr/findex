<?php

namespace App\Services;

use RuntimeException;

/**
 * Keeps the scrapers pointed at the public internet.
 *
 * The scraper fetches a URL from organization_sources and follows up to five
 * redirects. Both halves matter: the URL is operator-set and reasonably
 * trusted, but a redirect is chosen by whichever bank website we just asked -
 * so any site on the scrape list can send our server to
 * http://169.254.169.254/ (cloud credentials), a loopback admin port, or
 * anything else reachable from inside the network. The response body never
 * reaches a log, which makes it a blind request rather than a read - but a
 * blind request to Redis or a metadata endpoint is still a request.
 *
 * Known limit, stated rather than papered over: this resolves the host and
 * checks the addresses, so a name that answers publicly on one lookup and
 * privately on the next (DNS rebinding) is not fully closed off. Doing that
 * properly means pinning the resolved address for the connection itself, which
 * Guzzle cannot express - it needs cURL's CURLOPT_RESOLVE. That is worth doing
 * if this ever fetches a user-supplied URL; for an operator-set list plus
 * redirect checking, this is the proportionate guard.
 */
class OutboundUrlGuard
{
    /**
     * Reserved ranges nothing on the public internet should resolve into.
     * Sourced from the IANA special-purpose registries rather than invented.
     */
    private const BLOCKED_V4 = [
        '0.0.0.0/8',          // "this network"
        '10.0.0.0/8',         // private
        '100.64.0.0/10',      // carrier-grade NAT
        '127.0.0.0/8',        // loopback
        '169.254.0.0/16',     // link-local - the cloud metadata endpoint
        '172.16.0.0/12',      // private
        '192.0.0.0/24',       // IETF protocol assignments
        '192.168.0.0/16',     // private
        '198.18.0.0/15',      // benchmarking
        '224.0.0.0/4',        // multicast
        '240.0.0.0/4',        // reserved
    ];

    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException("Refusing to fetch a malformed URL: {$url}");
        }

        // No file://, gopher://, ftp:// - a scraper reads web pages.
        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new RuntimeException("Refusing to fetch scheme [{$parts['scheme']}]: {$url}");
        }

        // parse_url keeps the brackets on an IPv6 literal, which would send
        // "[::1]" to the resolver and get blocked for the wrong reason.
        $host = trim($parts['host'], '[]');

        foreach ($this->addressesFor($host) as $address) {
            if ($this->isBlocked($address)) {
                throw new RuntimeException(
                    "Refusing to fetch {$host} - it resolves to the non-public address {$address}."
                );
            }
        }
    }

    /** @return array<int, string> */
    private function addressesFor(string $host): array
    {
        // A literal address needs no lookup - and must still be checked.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        $addresses = array_values(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));

        if ($addresses === []) {
            // A host that will not resolve cannot be fetched anyway; failing
            // here gives a clearer message than a timeout twenty seconds later.
            throw new RuntimeException("Refusing to fetch {$host} - it does not resolve.");
        }

        return $addresses;
    }

    private function isBlocked(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($address);

            if ($packed === false) {
                return true;
            }

            // ::1 loopback, fc00::/7 unique-local, fe80::/10 link-local.
            if ($address === '::1' || str_starts_with($address, 'fc') || str_starts_with($address, 'fd')
                || str_starts_with(strtolower($address), 'fe8') || str_starts_with(strtolower($address), 'fe9')
                || str_starts_with(strtolower($address), 'fea') || str_starts_with(strtolower($address), 'feb')) {
                return true;
            }

            // ::ffff:127.0.0.1 and friends - an IPv4 address wearing a hat.
            if (str_contains($address, '.')) {
                $mapped = substr($address, strrpos($address, ':') + 1);

                return filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $this->isBlocked($mapped) : true;
            }

            return false;
        }

        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true;
        }

        foreach (self::BLOCKED_V4 as $range) {
            [$subnet, $bits] = explode('/', $range);

            $mask = -1 << (32 - (int) $bits);

            if ((ip2long($address) & $mask) === (ip2long($subnet) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
