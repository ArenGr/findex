<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The bare domain always redirects into a locale-prefixed URL (see
     * routes/web.php) - it never serves 200 directly. Which locale it
     * redirects to depends on the request's Accept-Language header, so
     * this only pins down the invariant that matters: it's always one of
     * the actually-supported locales, never a bare 200 or an unsupported one.
     */
    public function test_bare_domain_redirects_to_a_supported_locale(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $locale = ltrim(parse_url($location, PHP_URL_PATH), '/');
        $this->assertContains($locale, array_keys(config('localization.available')));
    }
}
