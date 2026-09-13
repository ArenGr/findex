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

        Cache::flush();
    }

    // Refuses to run unless the suite is pointed at the throwaway in-memory database.
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
