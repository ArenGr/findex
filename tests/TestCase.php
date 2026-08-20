<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->assertRunningAgainstTheTestDatabase();

        // The 'array' cache store (CACHE_STORE=array in phpunit.xml) lives in
        // process memory for the life of the test run - unlike the database,
        // it isn't reset by RefreshDatabase, so a value cached by one test can
        // otherwise leak into the next test's assertions.
        Cache::flush();
    }

    /**
     * Refuses to run unless the suite is pointed at the throwaway in-memory
     * database.
     *
     * phpunit.xml sets DB_CONNECTION=sqlite / DB_DATABASE=:memory: through
     * <env> entries - but those are read from the environment, and a cached
     * config (bootstrap/cache/config.php, written by `php artisan
     * config:cache`) is loaded in preference to them. So with a stale cached
     * config present, the entire suite silently runs against whatever the
     * real .env points at, and RefreshDatabase truncates it. That has already
     * happened once here and destroyed the local development database.
     *
     * Failing on the first test is recoverable; discovering it afterwards is
     * not, so this is deliberately a hard abort rather than a warning.
     */
    private function assertRunningAgainstTheTestDatabase(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:') {
            return;
        }

        throw new RuntimeException(sprintf(
            "Refusing to run tests against '%s' (%s).\n".
            "Expected the in-memory SQLite database from phpunit.xml.\n".
            'A cached config overrides those settings - run `php artisan config:clear` and try again.',
            $connection->getDatabaseName(),
            $connection->getDriverName(),
        ));
    }
}
