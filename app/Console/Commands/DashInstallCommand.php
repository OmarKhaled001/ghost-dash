<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DatabaseProblem;
use App\Exceptions\InstallationFailed;
use App\Models\Admin;
use App\Repositories\Contracts\AdminRepository;
use App\Services\AdminService;
use App\Settings\GeneralSettings;
use App\Support\Installer\DatabaseProbe;
use App\Support\Installer\EnvFile;
use App\Support\Installer\LocalSite;
use App\Support\Locales;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process as SymfonyProcess;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Takes a fresh checkout to a working panel.
 *
 * Every step first looks at what is already there (an .env value, a
 * database, a migration, a super admin) and only does what is missing.
 * That is what makes the command safe to run twice and lets it pick up
 * where it stopped after a failure, without keeping any state of its own.
 */
#[AsCommand(name: 'dash:install', description: 'Install GhostDash: environment, database, permissions, first super admin and assets')]
#[Signature('dash:install
        {--app-name= : Application name (default: the directory name)}
        {--app-url= : Application URL}
        {--locale= : Default language, en or ar}
        {--db-host= : MySQL host}
        {--db-port= : MySQL port}
        {--db-database= : Database name (default: the directory name in snake_case)}
        {--db-username= : MySQL user}
        {--db-password= : MySQL password. Prefer the GHOSTDASH_DB_PASSWORD environment variable}
        {--create-db : Create the database (and the testing database) when missing, without asking}
        {--skip-testing-db : Do not create the testing database or .env.testing}
        {--admin-name= : Name of the first super admin}
        {--admin-email= : Email of the first super admin}
        {--admin-password= : Password of the first super admin. Prefer GHOSTDASH_ADMIN_PASSWORD}
        {--demo : Seed demo data. Only allowed when APP_ENV=local}
        {--seo : Switch on SEO: site-wide and per page, <x-seo> and GET /api/v1/seo}
        {--skip-assets : Do not run npm install and npm run build}
        {--no-git : Do not initialise a git repository}
        {--serve : Start composer dev when the installer finishes}
        {--no-serve : Do not offer to start the development server}
        {--testing-db : In production: create the testing database anyway}
        {--assets : In production: build assets anyway}
        {--git : In production: initialise git anyway}')]
final class DashInstallCommand extends Command
{
    private EnvFile $env;

    /**
     * True on a server driven by a deploy script. The installer then leaves
     * .env, assets, git and the testing database alone unless told otherwise.
     */
    private bool $unattendedProduction = false;

    private ?string $adminEmail = null;

    public function handle(AdminRepository $admins, AdminService $adminService): int
    {
        // Always .env, even when the command itself runs under another
        // environment file (APP_ENV=testing loads .env.testing, for one).
        $this->env = new EnvFile($this->laravel->environmentPath().DIRECTORY_SEPARATOR.'.env');
        $this->unattendedProduction = $this->laravel->isProduction() && ! $this->input->isInteractive();

        try {
            $this->refuseDemoOutsideLocal();

            $this->heading(0, 'Bootstrap');
            $this->bootstrapEnvironment();

            $this->heading(1, 'Application');
            $this->configureApplication();
            $this->addSeo();

            $this->heading(2, 'Database');
            $database = $this->configureDatabase();

            $this->heading(3, 'Testing database');
            $this->configureTestingDatabase($database);

            $this->heading(4, 'Schema and access');
            $firstInstall = $this->prepareSchema($admins);

            $this->heading(5, 'Super admin');
            $this->createSuperAdmin($admins, $adminService);

            $this->heading(6, 'Demo data');
            $this->seedDemoData();

            $this->heading(7, 'Assets');
            $this->buildAssets();

            $this->heading(8, 'Git');
            $this->initialiseGit();

            $this->heading(9, 'Summary');
            $this->summarise($firstInstall);
        } catch (InstallationFailed $failure) {
            $this->newLine();
            $this->components->error($failure->getMessage());
            $this->line('  Nothing that already worked was undone. Fix the problem above, then continue with:');
            $this->line('    <options=bold>php artisan dash:install</>');
            $this->newLine();

            return self::FAILURE;
        }

        if ($this->shouldServe()) {
            $this->serve();
        }

        return self::SUCCESS;
    }

    /**
     * Checked before anything is written: an option that will be refused
     * should not leave a half-installed application behind first.
     */
    private function refuseDemoOutsideLocal(): void
    {
        if ($this->option('demo') && ! $this->laravel->environment('local')) {
            throw new InstallationFailed(
                'Demo data is only available when APP_ENV=local. Every demo account has the password "password", '
                ."which must never exist in the [{$this->laravel->environment()}] environment. Remove --demo."
            );
        }
    }

    private function bootstrapEnvironment(): void
    {
        if (! $this->env->exists()) {
            $this->env->createFrom(base_path('.env.example'));
            $this->done('Created .env from .env.example');
        } else {
            $this->skipped('.env exists');
        }

        if (! $this->env->has('APP_KEY')) {
            $key = $this->generateKey();
            $this->env->set(['APP_KEY' => $key]);

            // This process read its configuration before the key existed.
            if (blank(config('app.key'))) {
                config(['app.key' => $key]);
            }

            $this->done('Generated APP_KEY');
        } else {
            $this->skipped('APP_KEY is set');
        }
    }

    private function configureApplication(): void
    {
        if ($this->unattendedProduction) {
            $this->skipped('Keeping the application settings from .env (production, --no-interaction)');

            return;
        }

        $directory = basename(base_path());

        $name = $this->value('app-name', 'Application name', $this->existing('APP_NAME', 'GhostDash') ?? Str::headline($directory));

        $url = $this->value(
            'app-url',
            'Application URL',
            $this->existing('APP_URL', 'http://localhost:8000') ?? LocalSite::defaultUrl(base_path()),
            fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_URL) === false ? 'Enter a full URL, such as http://localhost:8000.' : null,
        );

        $locale = $this->pick(
            'locale',
            'Default language',
            Locales::options(),
            $this->env->get('APP_LOCALE') ?? 'en',
        );

        $this->env->set([
            'APP_NAME' => $name,
            'APP_URL' => rtrim($url, '/'),
            'APP_LOCALE' => $locale,
        ]);

        config(['app.name' => $name, 'app.url' => rtrim($url, '/'), 'app.locale' => $locale]);

        $this->done("{$name} at ".rtrim($url, '/')." ({$locale})");
    }

    /**
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    private function configureDatabase(): array
    {
        $attempt = 0;

        while (true) {
            $attempt++;
            $credentials = $this->databaseCredentials(reask: $attempt > 1);

            if (! $this->unattendedProduction) {
                $this->env->set([
                    'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => $credentials['host'],
                    'DB_PORT' => $credentials['port'],
                    'DB_DATABASE' => $credentials['database'],
                    'DB_USERNAME' => $credentials['username'],
                    'DB_PASSWORD' => $credentials['password'],
                ]);
            }

            $probe = new DatabaseProbe($credentials['host'], $credentials['port'], $credentials['username'], $credentials['password']);
            $problem = $this->ensureDatabase($probe, $credentials['database']);

            if (! $problem instanceof DatabaseProblem) {
                break;
            }

            $this->newLine();
            $this->components->error($probe->errorMessage() ?? "The database '{$credentials['database']}' is not usable.");

            foreach (explode("\n", $problem->hint($credentials['host'], $credentials['port'], $credentials['database'], $credentials['username'])) as $line) {
                $this->line('  '.$line);
            }

            $this->newLine();

            if (! $this->input->isInteractive() || ! confirm('Enter the database details again?', default: true)) {
                throw new InstallationFailed('The database connection could not be established.');
            }
        }

        // Point this process at the database that was just confirmed: it
        // booted with whatever .env held before, possibly the placeholder
        // values from .env.example.
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $credentials['host'],
            'database.connections.mysql.port' => $credentials['port'],
            'database.connections.mysql.database' => $credentials['database'],
            'database.connections.mysql.username' => $credentials['username'],
            'database.connections.mysql.password' => $credentials['password'],
        ]);

        DB::purge('mysql');

        $this->done("Connected to {$credentials['database']} on {$credentials['host']}:{$credentials['port']} (MySQL {$probe->serverVersion()})");

        return $credentials;
    }

    /**
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    private function databaseCredentials(bool $reask): array
    {
        if ($this->unattendedProduction) {
            return [
                'host' => (string) config('database.connections.mysql.host'),
                'port' => (int) config('database.connections.mysql.port'),
                'database' => (string) config('database.connections.mysql.database'),
                'username' => (string) config('database.connections.mysql.username'),
                'password' => (string) config('database.connections.mysql.password'),
            ];
        }

        // On a second attempt the options are what just failed, so they are
        // offered as defaults to correct rather than silently reused.
        $ask = fn (string $option, string $label, string $default): string => $reask
            ? text($label, default: $this->env->get('DB_'.Str::upper(Str::after($option, 'db-'))) ?? $default, required: true)
            : $this->value($option, $label, $default);

        $host = $ask('db-host', 'MySQL host', $this->env->get('DB_HOST') ?? '127.0.0.1');
        $port = $ask('db-port', 'MySQL port', $this->env->get('DB_PORT') ?? '3306');
        $database = $ask('db-database', 'Database name', $this->existing('DB_DATABASE', 'my_app') ?? $this->defaultDatabaseName());
        $username = $ask('db-username', 'MySQL user', $this->env->get('DB_USERNAME') ?? 'root');

        // The name ends up inside a CREATE DATABASE statement, where it
        // cannot be a bound parameter.
        if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $database) !== 1) {
            throw new InstallationFailed("'{$database}' is not a usable database name. Use letters, digits and underscores only.");
        }

        return [
            'host' => $host,
            'port' => (int) $port,
            'database' => $database,
            'username' => $username,
            'password' => $this->databasePassword($reask),
        ];
    }

    private function databasePassword(bool $reask): string
    {
        if (! $reask) {
            $given = $this->option('db-password') ?? (getenv('GHOSTDASH_DB_PASSWORD') === false ? null : getenv('GHOSTDASH_DB_PASSWORD'));

            if (is_string($given)) {
                return $given;
            }
        }

        $existing = (string) $this->env->get('DB_PASSWORD', '');

        if (! $this->input->isInteractive()) {
            return $existing;
        }

        $hint = $existing === '' ? 'Leave empty for no password.' : 'Leave empty to keep the password already in .env.';
        $entered = password('MySQL password', hint: $hint);

        return $entered === '' ? $existing : $entered;
    }

    /**
     * Make sure the database exists and is usable, creating it when allowed.
     * Returns what is wrong, or null when all is well.
     */
    private function ensureDatabase(DatabaseProbe $probe, string $database): ?DatabaseProblem
    {
        if (! $probe->canConnect()) {
            return $probe->problem() ?? DatabaseProblem::Other;
        }

        if (! $probe->databaseExists($database)) {
            $create = $this->option('create-db')
                || ($this->input->isInteractive() && confirm("The database '{$database}' does not exist. Create it?", default: true));

            if (! $create) {
                return DatabaseProblem::UnknownDatabase;
            }

            if (! $probe->createDatabase($database)) {
                return $probe->problem() ?? DatabaseProblem::Other;
            }

            $this->done("Created database {$database} (utf8mb4, utf8mb4_unicode_ci)");
        }

        return $probe->canUse($database) ? null : ($probe->problem() ?? DatabaseProblem::Other);
    }

    /**
     * @param  array{host: string, port: int, database: string, username: string, password: string}  $database
     */
    private function configureTestingDatabase(array $database): void
    {
        if ($this->option('skip-testing-db')) {
            $this->skipped('Skipped (--skip-testing-db)');

            return;
        }

        if ($this->unattendedProduction && ! $this->option('testing-db')) {
            $this->skipped('Skipped in production. Pass --testing-db to create it anyway');

            return;
        }

        $name = $database['database'].'_testing';
        $probe = new DatabaseProbe($database['host'], $database['port'], $database['username'], $database['password']);

        if ($probe->databaseExists($name)) {
            $this->skipped("Database {$name} exists");
        } elseif ($this->option('create-db') || ($this->input->isInteractive() && confirm("Create the testing database '{$name}'?", default: true))) {
            $probe->createDatabase($name)
                ? $this->done("Created database {$name}")
                : $this->components->warn("Could not create {$name}: ".$probe->errorMessage());
        } else {
            $this->components->warn("The testing database {$name} does not exist. Create it before running the tests, or re-run with --create-db.");
        }

        $testing = new EnvFile(dirname($this->env->path()).DIRECTORY_SEPARATOR.'.env.testing');

        if (! $testing->exists()) {
            $testing->createFrom(base_path('.env.testing.example'));
        }

        $testing->set([
            'APP_NAME' => (string) config('app.name'),
            'DB_HOST' => $database['host'],
            'DB_PORT' => $database['port'],
            'DB_DATABASE' => $name,
            'DB_USERNAME' => $database['username'],
            'DB_PASSWORD' => $database['password'],
        ]);

        // Its own key, so nothing encrypted while testing can be read with
        // the real one and the other way round.
        if (! $testing->has('APP_KEY')) {
            $testing->set(['APP_KEY' => $this->generateKey()]);
        }

        $this->done('Wrote .env.testing');
    }

    /**
     * @return bool Whether this is the first installation (no super admin yet).
     */
    private function prepareSchema(AdminRepository $admins): bool
    {
        $this->runArtisan('migrate', ['--force' => true]);

        file_exists(public_path('storage'))
            ? $this->skipped('public/storage is linked')
            : $this->runArtisan('storage:link');

        $this->runArtisan('dash:sync-permissions');

        $firstInstall = ! $admins->superAdminExists();

        // The settings migration seeds defaults from config. On a first
        // install, or when asked explicitly, they follow what was entered
        // above. Later runs leave alone what an admin has since changed
        // on the settings page.
        if ($firstInstall || $this->option('app-name') !== null || $this->option('locale') !== null) {
            $settings = resolve(GeneralSettings::class);
            $settings->app_name = (string) config('app.name');
            $settings->default_locale = Locales::isSupported((string) config('app.locale')) ? (string) config('app.locale') : 'en';
            $settings->save();

            $this->done('Stored the default settings');
        } else {
            $this->skipped('Settings kept as they are');
        }

        return $firstInstall;
    }

    private function addSeo(): void
    {
        if (config('ghostdash.seo.enabled')) {
            $this->skipped('SEO is on');

            return;
        }

        if ($this->unattendedProduction) {
            $this->skipped('SEO left as configured (set GHOSTDASH_SEO=true on the server to switch it on)');

            return;
        }

        $wanted = $this->option('seo')
            || ($this->input->isInteractive() && confirm('Switch on SEO (site-wide and per page)?', default: false));

        if (! $wanted) {
            $this->skipped('SEO is off (php artisan dash:seo switches it on later)');

            return;
        }

        $this->env->set(['GHOSTDASH_SEO' => 'true']);
        config(['ghostdash.seo.enabled' => true]);

        $this->done('Switched on SEO');
    }

    private function createSuperAdmin(AdminRepository $admins, AdminService $adminService): void
    {
        if ($admins->superAdminExists()) {
            $this->skipped('A super admin already exists');

            return;
        }

        $name = $this->value('admin-name', 'Your name', '', required: true);
        $email = $this->value('admin-email', 'Your email address', '', required: true);
        $password = $this->adminPassword();

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique(Admin::class, 'email')],
                'password' => ['required', 'string', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            throw new InstallationFailed(
                "The super admin could not be created:\n  - ".implode("\n  - ", $validator->errors()->all())
                .(! $this->input->isInteractive() ? "\n  Pass --admin-name, --admin-email and --admin-password (or GHOSTDASH_ADMIN_PASSWORD)." : '')
            );
        }

        $admin = $adminService->createFirstSuperAdmin(['name' => $name, 'email' => $email, 'password' => $password]);

        $this->adminEmail = $admin->email;
        $this->done("Created super admin {$admin->email}");
    }

    private function adminPassword(): string
    {
        $given = $this->option('admin-password') ?? (getenv('GHOSTDASH_ADMIN_PASSWORD') === false ? null : getenv('GHOSTDASH_ADMIN_PASSWORD'));

        if (is_string($given) && $given !== '') {
            return $given;
        }

        if (! $this->input->isInteractive()) {
            return '';
        }

        // Asked twice, never echoed. A typo here would lock the only admin
        // out of a panel that has no other way in yet.
        while (true) {
            $first = password('Choose a password', required: true, hint: 'At least 12 characters, with upper and lower case letters and a number.');
            $second = password('Repeat the password', required: true);

            if ($first === $second) {
                return $first;
            }

            $this->components->error('The two passwords are not the same. Try again.');
        }
    }

    private function seedDemoData(): void
    {
        if (! $this->laravel->environment('local')) {
            $this->skipped('Only offered when APP_ENV=local');

            return;
        }

        $wanted = $this->option('demo')
            || ($this->input->isInteractive() && confirm('Add demo data (two limited admins and 25 users)?', default: false));

        if (! $wanted) {
            $this->skipped('Not requested');

            return;
        }

        $this->runArtisan('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        $this->done('Demo admins: support@example.com and auditor@example.com, password "'.DemoSeeder::PASSWORD.'"');
    }

    private function buildAssets(): void
    {
        if ($this->option('skip-assets')) {
            $this->skipped('Skipped (--skip-assets)');

            return;
        }

        if ($this->unattendedProduction && ! $this->option('assets')) {
            $this->skipped('Skipped in production. Build assets in your deploy script, or pass --assets');

            return;
        }

        if (file_exists(public_path('build/manifest.json'))) {
            $this->skipped('public/build/manifest.json exists');

            return;
        }

        if ((new ExecutableFinder)->find('npm') === null) {
            $this->components->warn('npm was not found, so the panel theme was not built. Install Node.js 22+, then run: npm install && npm run build');

            return;
        }

        foreach (['npm install', 'npm run build'] as $command) {
            $this->line("  <fg=gray>$ {$command}</>");

            $result = Process::path(base_path())
                ->forever()
                ->run($command, function (string $type, string $output): void {
                    $this->output->write($output);
                });

            if ($result->failed()) {
                throw new InstallationFailed("`{$command}` failed. Its output is above.");
            }
        }

        $this->done('Built the panel assets');
    }

    private function initialiseGit(): void
    {
        if ($this->option('no-git')) {
            $this->skipped('Skipped (--no-git)');

            return;
        }

        if ($this->unattendedProduction && ! $this->option('git')) {
            $this->skipped('Skipped in production. Pass --git to initialise a repository anyway');

            return;
        }

        if (is_dir(base_path('.git'))) {
            $this->skipped('Already a git repository');

            return;
        }

        if ((new ExecutableFinder)->find('git') === null) {
            $this->skipped('git was not found');

            return;
        }

        if ($this->input->isInteractive() && ! confirm('Initialise a git repository and make the first commit?', default: true)) {
            $this->skipped('Not requested');

            return;
        }

        $git = fn (string $command) => Process::path(base_path())->run($command);

        if ($git('git init')->failed()) {
            $this->components->warn('git init failed. Initialise the repository yourself.');

            return;
        }

        $git('git add -A');

        // Fails when git has no user.name / user.email yet. The repository
        // exists either way, and the installation is not worth failing for it.
        $git('git commit -m "chore: initial commit from GhostDash"')->successful()
            ? $this->done('Initialised a git repository with an initial commit')
            : $this->components->warn('Created the repository, but the first commit failed (is git user.name / user.email set?). Commit it yourself.');
    }

    private function summarise(bool $firstInstall): void
    {
        // Advice, not a gate: the doctor may warn about a scheduler that
        // cannot be running yet, and the installation is complete regardless.
        $this->call('dash:doctor');

        $panel = rtrim((string) config('app.url'), '/').'/'.config('ghostdash.panel.path');
        $email = $this->adminEmail ?? Admin::query()->role('super_admin')->orderBy('id')->value('email') ?? '(none)';

        $lines = [
            $firstInstall ? 'GhostDash is installed.' : 'GhostDash is up to date.',
            '',
            "Panel        {$panel}",
            "Super admin  {$email}",
            '',
            'Next:',
            '  composer dev              start the server, queue worker and Vite',
            '  php artisan dash:doctor   check the installation again',
            '  composer check            lint, analyse and test',
        ];

        $width = max(array_map(mb_strlen(...), $lines));

        $this->newLine();
        $this->line('  +'.str_repeat('-', $width + 2).'+');

        foreach ($lines as $line) {
            $this->line('  | '.$line.str_repeat(' ', $width - mb_strlen($line)).' |');
        }

        $this->line('  +'.str_repeat('-', $width + 2).'+');
        $this->newLine();
    }

    private function shouldServe(): bool
    {
        if ($this->laravel->isProduction() || $this->option('no-serve')) {
            return false;
        }

        if ($this->option('serve')) {
            return true;
        }

        return $this->input->isInteractive() && confirm('Start the development server now (composer dev)?', default: true);
    }

    private function serve(): void
    {
        $this->line('  <fg=gray>$ composer dev</>  (Ctrl+C to stop)');

        Process::path(base_path())
            ->forever()
            ->tty(SymfonyProcess::isTtySupported())
            ->run('composer dev', function (string $type, string $output): void {
                $this->output->write($output);
            });
    }

    /**
     * An option when given, a question when someone is there to answer it,
     * and the default otherwise.
     *
     * @param  (callable(string): ?string)|null  $validate
     */
    private function value(string $option, string $label, string $default, ?callable $validate = null, bool $required = false): string
    {
        $given = $this->option($option);

        if (is_string($given) && $given !== '') {
            return $given;
        }

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return text($label, default: $default, required: $required || $default !== '', validate: $validate);
    }

    /**
     * @param  array<string, string>  $options
     */
    private function pick(string $option, string $label, array $options, string $default): string
    {
        $given = $this->option($option);

        if (is_string($given) && $given !== '') {
            if (! array_key_exists($given, $options)) {
                throw new InstallationFailed("--{$option} must be one of: ".implode(', ', array_keys($options)).'.');
            }

            return $given;
        }

        $default = array_key_exists($default, $options) ? $default : (string) array_key_first($options);

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return (string) select($label, $options, default: $default);
    }

    /**
     * The value already in .env, unless it is still the placeholder that
     * .env.example ships with. A placeholder is not a decision someone made,
     * so the installer proposes a better default in its place.
     */
    private function existing(string $key, string $placeholder): ?string
    {
        $value = $this->env->get($key);

        return blank($value) || $value === $placeholder ? null : $value;
    }

    private function defaultDatabaseName(): string
    {
        $name = Str::of(basename(base_path()))->snake()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_')->toString();

        return $name === '' ? 'ghostdash' : $name;
    }

    private function generateKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function runArtisan(string $command, array $arguments = []): void
    {
        try {
            $exitCode = $this->call($command, $arguments);
        } catch (Throwable $exception) {
            throw new InstallationFailed("`php artisan {$command}` failed: ".$exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        if ($exitCode !== self::SUCCESS) {
            throw new InstallationFailed("`php artisan {$command}` failed. Its output is above.");
        }
    }

    private function heading(int $number, string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>  {$number}. {$title}</>");
    }

    private function done(string $message): void
    {
        $this->line("  <fg=green>done</>  {$message}");
    }

    private function skipped(string $message): void
    {
        $this->line("  <fg=gray>skip</>  {$message}");
    }
}
