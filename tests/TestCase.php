<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->refuseToRunAgainstARealDatabase();

        // Symfony keeps trusted host patterns in a static property. A test
        // that leaves the local environment sets them, and they would then
        // apply to every later test in the same process.
        Request::setTrustedHosts([]);

        // The panel theme is a Vite entry point; tests should not need a build.
        $this->withoutVite();

        // Filament pads failed logins to a fixed duration to hide timing
        // differences. That is 200ms of sleep per negative test for nothing.
        config(['auth.timebox_duration' => 0]);
    }

    /**
     * Without a .env.testing file Laravel quietly falls back to .env, and the
     * first RefreshDatabase would then wipe the developer's real database.
     */
    private function refuseToRunAgainstARealDatabase(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (! str_contains($database, '_testing')) {
            throw new RuntimeException(
                "Refusing to run tests against the [{$database}] database: its name does not contain \"_testing\". "
                .'Create .env.testing (run `php artisan dash:install`, or copy .env.testing.example) first.'
            );
        }
    }
}
