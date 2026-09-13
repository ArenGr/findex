<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_bare_domain_redirects_to_a_supported_locale(): void
    {
        $response = $this->get('/');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $locale = ltrim(parse_url($location, PHP_URL_PATH), '/');
        $this->assertContains($locale, array_keys(config('localization.available')));
    }
}
