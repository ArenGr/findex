<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Font preloads have to resolve against the host the visitor actually used.
 *
 * They did not: App\Support\FontPreloads cached the output of asset(), which
 * builds an absolute URL from the current request. Whichever host warmed the
 * cache was then served to everyone - a cache warmed on 127.0.0.1 handed
 * localhost visitors cross-origin <link rel="preload"> tags. Fonts are always
 * fetched in CORS mode, so the browser blocked them, and the face arrived late
 * through the stylesheet's own relative URL instead: a visible swap on every
 * heading, on every page load.
 */
class FontPreloadTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function preloadedFontHrefs(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();

        preg_match_all('/<link[^>]*rel="preload"[^>]*as="font"[^>]*>/i', $html, $tags);

        return collect($tags[0])
            ->map(fn (string $tag) => preg_match('/href="([^"]+)"/', $tag, $m) ? $m[1] : null)
            ->filter()
            ->values()
            ->all();
    }

    public function test_font_preloads_use_the_host_the_page_was_requested_from(): void
    {
        $hrefs = $this->preloadedFontHrefs('http://localhost/en/insurance/auto');

        $this->assertNotEmpty($hrefs, 'the page should preload at least one font');

        foreach ($hrefs as $href) {
            $this->assertStringStartsWith('http://localhost/', $href);
        }
    }

    /**
     * The regression itself: a second request from a different host must not
     * be served the first host's URLs out of the cache.
     */
    public function test_a_second_host_is_not_served_the_first_hosts_cached_urls(): void
    {
        $this->preloadedFontHrefs('http://localhost/en/insurance/auto');

        $hrefs = $this->preloadedFontHrefs('http://127.0.0.1/en/insurance/auto');

        $this->assertNotEmpty($hrefs);

        foreach ($hrefs as $href) {
            $this->assertStringStartsWith('http://127.0.0.1/', $href);
            $this->assertStringNotContainsString('localhost', $href);
        }
    }

    /** The heading face is the one whose absence shows up as a swap. */
    public function test_the_heading_font_is_preloaded(): void
    {
        $hrefs = $this->preloadedFontHrefs('http://localhost/en/insurance/auto');

        $this->assertNotEmpty(
            array_filter($hrefs, fn (string $href) => str_contains($href, 'montserrat')),
            'Montserrat must be preloaded or headings swap in late'
        );
    }
}
