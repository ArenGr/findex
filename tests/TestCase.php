<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    // The 'array' cache store (CACHE_STORE=array in phpunit.xml) lives in
    // process memory for the life of the test run - unlike the database,
    // it isn't reset by RefreshDatabase, so a value cached by one test can
    // otherwise leak into the next test's assertions.
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }
}
