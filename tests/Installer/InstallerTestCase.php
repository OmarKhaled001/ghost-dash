<?php

declare(strict_types=1);

namespace Tests\Installer;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PDO;

/**
 * A sandbox for running dash:install for real: a scratch directory that
 * receives .env and .env.testing, and scratch databases that are dropped
 * afterwards. The MySQL server and credentials are those of the test suite.
 */
trait InstallerTestCase
{
    protected string $scratch;

    protected string $database;

    /** @var array<string, mixed> */
    private array $originalConnection;

    protected function setUpInstaller(): void
    {
        $this->scratch = storage_path('framework/testing/installer-'.Str::lower(Str::random(10)));
        $this->originalConnection = config('database.connections.mysql');

        // Named after the suite's own database, so that the one privilege
        // the README asks for (`<testing database>%`) covers these as well.
        // Cut to stay under MySQL's 64 characters with "_testing" appended.
        $this->database = Str::limit((string) $this->originalConnection['database'], 38, '').'_it_'.Str::lower(Str::random(8));

        File::ensureDirectoryExists($this->scratch);

        // The installer writes to wherever the application says .env lives.
        $this->app->useEnvironmentPath($this->scratch);

        // Nothing here should shell out to npm, git or composer.
        Process::preventStrayProcesses();
    }

    protected function tearDownInstaller(): void
    {
        config(['database.connections.mysql' => $this->originalConnection]);
        DB::purge('mysql');

        $pdo = $this->pdo();
        $pdo->exec("DROP DATABASE IF EXISTS `{$this->database}`");
        $pdo->exec("DROP DATABASE IF EXISTS `{$this->database}_testing`");

        File::deleteDirectory($this->scratch);
    }

    /**
     * Options for an unattended install into the scratch database.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function installOptions(array $overrides = []): array
    {
        return [
            '--no-interaction' => true,
            '--app-name' => 'Scratch App',
            '--app-url' => 'http://scratch.test',
            '--locale' => 'ar',
            '--db-host' => $this->originalConnection['host'],
            '--db-port' => (string) $this->originalConnection['port'],
            '--db-database' => $this->database,
            '--db-username' => $this->originalConnection['username'],
            '--db-password' => (string) $this->originalConnection['password'],
            '--create-db' => true,
            '--admin-name' => 'Omar Khaled',
            '--admin-email' => 'omar@example.com',
            '--admin-password' => 'A-very-strong-Passw0rd',
            '--skip-assets' => true,
            '--no-git' => true,
            ...$overrides,
        ];
    }

    protected function envContents(string $file = '.env'): string
    {
        return (string) File::get($this->scratch.'/'.$file);
    }

    protected function databaseExists(string $name): bool
    {
        $statement = $this->pdo()->prepare('SELECT 1 FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $statement->execute([$name]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * A connection that does not go through Laravel, so it keeps working
     * while the installer repoints the application's own connection.
     */
    protected function pdo(?string $database = null): PDO
    {
        $c = $this->originalConnection;
        $dsn = "mysql:host={$c['host']};port={$c['port']}".($database === null ? '' : ";dbname={$database}");

        return new PDO($dsn, $c['username'], (string) $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
}
