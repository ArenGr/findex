<?php

namespace Tests\Feature;

use App\Services\OutboundUrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * The scrapers fetch an operator-set URL and follow up to five redirects. The
 * URL is reasonably trusted; the redirect target is chosen by whichever bank
 * website we just asked, which is not the same thing at all.
 */
class OutboundUrlGuardTest extends TestCase
{
    private function guard(): OutboundUrlGuard
    {
        return new OutboundUrlGuard;
    }

    /** @return array<int, array{0: string}> */
    public static function blockedUrls(): array
    {
        return [
            // The one that matters most: cloud instance credentials.
            ['http://169.254.169.254/latest/meta-data/iam/security-credentials/'],
            ['http://127.0.0.1:6379/'],
            ['http://localhost/admin'],
            ['https://localhost:8000/'],
            ['http://10.0.0.5/'],
            ['http://192.168.1.1/'],
            ['http://172.16.0.1/'],
            ['http://[::1]/'],
            // Not a web page, and not something a scraper has any business
            // opening.
            ['file:///etc/passwd'],
            ['gopher://127.0.0.1:11211/'],
            ['not a url at all'],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_non_public_destinations_are_refused(string $url): void
    {
        $this->expectException(RuntimeException::class);

        $this->guard()->assertAllowed($url);
    }

    public function test_ordinary_public_pages_are_allowed(): void
    {
        // A literal public address, so the assertion does not depend on any
        // particular domain still existing.
        $this->guard()->assertAllowed('http://93.184.216.34/rates');
        $this->guard()->assertAllowed('https://1.1.1.1/');

        $this->addToAssertionCount(2);
    }

    /**
     * An IPv4 address wearing an IPv6 hat is still that address - and is the
     * usual way a naive check gets walked past.
     */
    public function test_ipv4_mapped_addresses_do_not_slip_through(): void
    {
        $this->expectException(RuntimeException::class);

        $this->guard()->assertAllowed('http://[::ffff:127.0.0.1]/');
    }
}
