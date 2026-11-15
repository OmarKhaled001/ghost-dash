<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DoctorResult;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Enums\Status;
use Spatie\Health\Facades\Health;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * The checks behind `php artisan dash:doctor`: is this installation set up
 * the way the kit expects?
 */
final readonly class DoctorService
{
    public const string MINIMUM_PHP = '8.4.0';

    public const string RECOMMENDED_MYSQL = '8.4.0';

    /**
     * The extensions README.md lists as requirements.
     */
    public const array EXTENSIONS = [
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'intl', 'mbstring',
        'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip',
    ];

    public function __construct(
        private Application $app,
        private BackupService $backups,
    ) {}

    /**
     * @return list<DoctorResult>
     */
    public function run(): array
    {
        return [
            $this->phpVersion(),
            $this->phpExtensions(),
            $this->appKey(),
            $this->debugMode(),
            $this->environment(),
            $this->hostNames(),
            ...$this->database(),
            $this->heartbeat('Scheduler', ScheduleCheck::class, 'Add the cron entry from README.md, or run `php artisan schedule:work`.'),
            $this->heartbeat('Queue worker', QueueCheck::class, 'Start one with `php artisan queue:work` (composer dev does) and keep the scheduler running.'),
            $this->telescope(),
            $this->mysqldump(),
            $this->backupDisk(),
            $this->storageLink(),
        ];
    }

    private function phpVersion(): DoctorResult
    {
        return version_compare(PHP_VERSION, self::MINIMUM_PHP, '>=')
            ? DoctorResult::pass('PHP version', PHP_VERSION)
            : DoctorResult::fail('PHP version', PHP_VERSION.' is too old. GhostDash needs PHP 8.4 or newer.');
    }

    private function phpExtensions(): DoctorResult
    {
        $missing = array_values(array_filter(self::EXTENSIONS, fn (string $extension): bool => ! extension_loaded($extension)));

        return $missing === []
            ? DoctorResult::pass('PHP extensions', 'All '.count(self::EXTENSIONS).' required extensions are loaded.')
            : DoctorResult::fail('PHP extensions', 'Missing: '.implode(', ', $missing).'.');
    }

    private function appKey(): DoctorResult
    {
        return filled(config('app.key'))
            ? DoctorResult::pass('APP_KEY', 'Set.')
            : DoctorResult::fail('APP_KEY', 'Empty. Run `php artisan key:generate`.');
    }

    private function debugMode(): DoctorResult
    {
        if (! config('app.debug')) {
            return DoctorResult::pass('APP_DEBUG', 'Off.');
        }

        return $this->app->isProduction()
            ? DoctorResult::fail('APP_DEBUG', 'On in production. Error pages would show code, queries and environment values. Set APP_DEBUG=false.')
            : DoctorResult::pass('APP_DEBUG', 'On, which is fine outside production.');
    }

    private function environment(): DoctorResult
    {
        $environment = (string) $this->app->environment();

        if ($this->app->isProduction()) {
            return DoctorResult::pass('APP_ENV', 'production');
        }

        // The doctor cannot know whether this machine is a server. A public
        // looking URL together with a non-production environment is the
        // closest thing to evidence that someone forgot to switch it.
        $host = $this->applicationHost();

        return $this->looksLocal($host)
            ? DoctorResult::pass('APP_ENV', "{$environment}, which is fine on a development machine.")
            : DoctorResult::warn('APP_ENV', "{$environment}, but APP_URL ({$host}) looks like a public address. Set APP_ENV=production on a server.");
    }

    /**
     * Outside local the application answers only for APP_URL's host, its
     * subdomains and TRUSTED_HOSTS. With APP_URL left at a development
     * address, that means it answers nobody, and says so with a bare 400.
     */
    private function hostNames(): DoctorResult
    {
        if ($this->app->environment('local')) {
            return DoctorResult::pass('Host names', 'Any host name is answered in the local environment.');
        }

        $host = $this->applicationHost();

        if ($this->looksLocal($host)) {
            return DoctorResult::warn('Host names', "APP_URL points at [{$host}]. Outside local, a request for any other host name is answered with 400. Set APP_URL to the public address.");
        }

        $extra = count((array) config('ghostdash.trusted_hosts', []));

        return DoctorResult::pass('Host names', "Answering for {$host} and its subdomains".($extra > 0 ? ", plus {$extra} pattern(s) from TRUSTED_HOSTS." : '.'));
    }

    private function applicationHost(): string
    {
        return (string) parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    private function looksLocal(string $host): bool
    {
        return $host === ''
            || in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || Str::endsWith($host, ['.test', '.local', '.localhost']);
    }

    /**
     * @return list<DoctorResult>
     */
    private function database(): array
    {
        try {
            $version = (string) DB::scalar('SELECT VERSION()');
        } catch (Throwable $exception) {
            return [
                DoctorResult::fail('Database connection', 'Cannot connect to MySQL: '.Str::limit($exception->getMessage(), 160)),
                DoctorResult::warn('MySQL version', 'Unknown, because the connection failed.'),
            ];
        }

        $connection = DoctorResult::pass('Database connection', 'Connected to '.config('database.connections.mysql.database').'.');

        if (Str::contains($version, 'MariaDB', ignoreCase: true)) {
            return [$connection, DoctorResult::warn('MySQL version', "{$version}. GhostDash is tested against MySQL 8.4, not MariaDB.")];
        }

        return [
            $connection,
            version_compare($version, self::RECOMMENDED_MYSQL, '>=')
                ? DoctorResult::pass('MySQL version', $version)
                : DoctorResult::warn('MySQL version', "{$version}. MySQL 8.0 is end-of-life; upgrade to 8.4 LTS."),
        ];
    }

    /**
     * Reuses the health checks, which already know where the heartbeats
     * are stored and how old one may be.
     *
     * @param  class-string<Check>  $checkClass
     */
    private function heartbeat(string $name, string $checkClass, string $advice): DoctorResult
    {
        $check = Health::registeredChecks()->first(fn (Check $check): bool => $check instanceof $checkClass);

        if (! $check instanceof Check) {
            return DoctorResult::warn($name, 'The health check is not registered.');
        }

        try {
            $healthy = $check->run()->status->equals(Status::ok());
        } catch (Throwable) {
            $healthy = false;
        }

        if ($healthy) {
            return DoctorResult::pass($name, 'Seen recently.');
        }

        // Right after an install nothing is running yet, and on a laptop
        // nothing has to be. On a server a silent scheduler means no
        // backups and no clean-ups, which is a failure.
        return $this->app->isProduction()
            ? DoctorResult::fail($name, "Not seen recently. {$advice}")
            : DoctorResult::warn($name, "Not seen recently. {$advice}");
    }

    private function telescope(): DoctorResult
    {
        if (! config('telescope.enabled')) {
            return DoctorResult::pass('Telescope', 'Disabled.');
        }

        return $this->app->environment('local')
            ? DoctorResult::pass('Telescope', 'Enabled, and open to everyone in the local environment.')
            : DoctorResult::warn('Telescope', 'Enabled. Only admins with view_telescope can open it, but it still records. Set TELESCOPE_ENABLED=false when you are done debugging.');
    }

    private function mysqldump(): DoctorResult
    {
        $directory = (string) config('database.connections.mysql.dump.dump_binary_path');
        $found = (new ExecutableFinder)->find('mysqldump', null, $directory === '' ? [] : [$directory]);

        return $found !== null
            ? DoctorResult::pass('mysqldump', $found)
            : DoctorResult::warn('mysqldump', 'Not found, so backups cannot dump the database. Install the MySQL client, or set DB_DUMP_BINARY_PATH to the directory that contains it.');
    }

    private function backupDisk(): DoctorResult
    {
        $disk = $this->backups->disk();

        try {
            $writable = $this->backups->isWritable();
        } catch (Throwable) {
            $writable = false;
        }

        return $writable
            ? DoctorResult::pass('Backup disk', "The [{$disk}] disk is reachable.")
            : DoctorResult::fail('Backup disk', "The [{$disk}] disk is not reachable or not writable. Check BACKUP_DISK and its credentials.");
    }

    private function storageLink(): DoctorResult
    {
        return file_exists(public_path('storage'))
            ? DoctorResult::pass('Storage link', 'public/storage exists.')
            : DoctorResult::warn('Storage link', 'public/storage is missing, so uploaded avatars and logos will not load. Run `php artisan storage:link`.');
    }
}
