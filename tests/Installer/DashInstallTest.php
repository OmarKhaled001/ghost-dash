<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Support\Installer\EnvFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;
use Tests\Installer\InstallerTestCase;

uses(InstallerTestCase::class);

beforeEach(fn () => $this->setUpInstaller());
afterEach(fn () => $this->tearDownInstaller());

describe('an unattended install', function (): void {
    it('switches SEO on when asked, and leaves it off otherwise', function (): void {
        config(['ghostdash.seo.enabled' => false]);

        $this->artisan('dash:install', $this->installOptions())->assertSuccessful();

        expect($this->envContents())->not->toContain('GHOSTDASH_SEO=true');

        $this->artisan('dash:install', $this->installOptions(['--seo' => true]))->assertSuccessful();

        expect($this->envContents())->toContain('GHOSTDASH_SEO=true')
            ->and(config('ghostdash.seo.enabled'))->toBeTrue();
    });

    it('goes from nothing to a working application', function (): void {
        expect(File::exists($this->scratch.'/.env'))->toBeFalse()
            ->and($this->databaseExists($this->database))->toBeFalse();

        $this->artisan('dash:install', $this->installOptions())->assertSuccessful();

        // .env
        $env = $this->envContents();
        expect($env)->toContain('APP_NAME="Scratch App"')
            ->toContain('APP_URL=http://scratch.test')
            ->toContain('APP_LOCALE=ar')
            ->toContain('DB_CONNECTION=mysql')
            ->toContain("DB_DATABASE={$this->database}")
            ->toMatch('/^APP_KEY=base64:.{40,}$/m');

        // Databases, with the required character set.
        expect($this->databaseExists($this->database))->toBeTrue()
            ->and($this->databaseExists($this->database.'_testing'))->toBeTrue();

        $charset = $this->pdo()->query("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$this->database}'")->fetch(PDO::FETCH_NUM);
        expect($charset)->toBe(['utf8mb4', 'utf8mb4_unicode_ci']);

        // .env.testing, with its own key.
        $testing = $this->envContents('.env.testing');
        preg_match('/^APP_KEY=(.+)$/m', $env, $key);
        preg_match('/^APP_KEY=(.+)$/m', $testing, $testingKey);

        expect($testing)->toContain("DB_DATABASE={$this->database}_testing")
            ->and($testingKey[1])->toStartWith('base64:')
            ->not->toBe($key[1]);

        // Schema, permissions, settings and the super admin, all in the new database.
        expect(DB::connection()->getDatabaseName())->toBe($this->database);

        $admin = Admin::query()->where('email', 'omar@example.com')->firstOrFail();

        expect($admin->name)->toBe('Omar Khaled')
            ->and($admin->isSuperAdmin())->toBeTrue()
            ->and($admin->is_active)->toBeTrue()
            ->and($admin->hasVerifiedEmail())->toBeTrue()
            ->and(Hash::check('A-very-strong-Passw0rd', $admin->password))->toBeTrue()
            ->and($admin->can('view_any:admin'))->toBeTrue()
            ->and($admin->can('view_telescope'))->toBeTrue()
            ->and(Role::query()->where('name', 'super_admin')->where('guard_name', 'admin')->count())->toBe(1);

        $settings = resolve(GeneralSettings::class)->refresh();
        expect($settings->app_name)->toBe('Scratch App')
            ->and($settings->default_locale)->toBe('ar');
    });

    it('never puts a password in its output', function (): void {
        $this->artisan('dash:install', $this->installOptions())
            ->doesntExpectOutputToContain('A-very-strong-Passw0rd')
            ->assertSuccessful();
    });

    it('reads the passwords from the environment', function (): void {
        putenv('GHOSTDASH_ADMIN_PASSWORD=From-the-environment-1');
        putenv('GHOSTDASH_DB_PASSWORD='.config('database.connections.mysql.password'));

        try {
            $this->artisan('dash:install', $this->installOptions(['--admin-password' => null, '--db-password' => null]))->assertSuccessful();
        } finally {
            putenv('GHOSTDASH_ADMIN_PASSWORD');
            putenv('GHOSTDASH_DB_PASSWORD');
        }

        expect(Hash::check('From-the-environment-1', Admin::query()->firstOrFail()->password))->toBeTrue();
    });

    it('writes passwords with special characters to .env so they read back intact', function (): void {
        // Cannot sign in to MySQL with this, which is not the point: the
        // value must survive the round trip through the file unchanged.
        $awkward = 'p@ss "word" $1 #hash \\slash';

        $this->artisan('dash:install', $this->installOptions(['--db-password' => $awkward]))->assertFailed();

        expect(new EnvFile($this->scratch.'/.env')->get('DB_PASSWORD'))->toBe($awkward);
    });

    it('skips the testing database on request', function (): void {
        $this->artisan('dash:install', $this->installOptions(['--skip-testing-db' => true]))->assertSuccessful();

        expect($this->databaseExists($this->database.'_testing'))->toBeFalse()
            ->and(File::exists($this->scratch.'/.env.testing'))->toBeFalse();
    });

    it('does not run npm, git or composer when told to skip them', function (): void {
        Process::fake();

        $this->artisan('dash:install', $this->installOptions())->assertSuccessful();

        Process::assertNothingRan();
    });

    it('builds assets when the manifest is missing and npm is available', function (): void {
        Process::fake();
        $manifest = public_path('build/manifest.json');
        $backup = File::exists($manifest) ? File::get($manifest) : null;
        File::delete($manifest);

        try {
            $this->artisan('dash:install', $this->installOptions(['--skip-assets' => false]))->assertSuccessful();
        } finally {
            if ($backup !== null) {
                File::ensureDirectoryExists(dirname($manifest));
                File::put($manifest, $backup);
            }
        }

        Process::assertRan('npm install');
        Process::assertRan('npm run build');
    })->skip(fn (): bool => (new ExecutableFinder)->find('npm') === null, 'npm is not installed');
});

describe('running it again', function (): void {
    it('is safe and changes nothing', function (): void {
        $this->artisan('dash:install', $this->installOptions())->assertSuccessful();

        $env = $this->envContents();
        $testing = $this->envContents('.env.testing');
        $admin = Admin::query()->firstOrFail();
        $permissions = DB::table('permissions')->count();
        $migrations = DB::table('migrations')->count();

        $this->artisan('dash:install', $this->installOptions())
            ->expectsOutputToContain('A super admin already exists')
            ->assertSuccessful();

        expect($this->envContents())->toBe($env)
            ->and($this->envContents('.env.testing'))->toBe($testing)
            ->and(Admin::query()->count())->toBe(1)
            ->and(Admin::query()->firstOrFail()->password)->toBe($admin->password)
            ->and(DB::table('permissions')->count())->toBe($permissions)
            ->and(DB::table('migrations')->count())->toBe($migrations)
            ->and(Role::query()->where('name', 'super_admin')->count())->toBe(1);
    });

    it('does not create a second super admin from different details', function (): void {
        $this->artisan('dash:install', $this->installOptions())->assertSuccessful();

        $this->artisan('dash:install', $this->installOptions(['--admin-email' => 'someone-else@example.com']))->assertSuccessful();

        expect(Admin::query()->pluck('email')->all())->toBe(['omar@example.com']);
    });

    it('keeps settings an admin changed since the first install', function (): void {
        $this->artisan('dash:install', $this->installOptions())->assertSuccessful();

        $settings = resolve(GeneralSettings::class);
        $settings->app_name = 'Renamed in the panel';
        $settings->save();

        // Unattended re-run with no explicit name or language, as a deploy script would do.
        $this->artisan('dash:install', $this->installOptions(['--app-name' => null, '--locale' => null]))->assertSuccessful();

        expect(resolve(GeneralSettings::class)->refresh()->app_name)->toBe('Renamed in the panel');
    });

    it('uses what is already in .env when options are left out', function (): void {
        $this->artisan('dash:install', $this->installOptions())->assertSuccessful();
        $env = $this->envContents();

        $this->artisan('dash:install', [
            '--no-interaction' => true,
            '--skip-assets' => true,
            '--no-git' => true,
        ])->assertSuccessful();

        expect($this->envContents())->toBe($env);
    });
});

describe('resuming after a failure', function (): void {
    it('continues from the step that failed, keeping what was done', function (): void {
        // The password does not meet the policy, so step 5 fails after the
        // environment, both databases, the schema and the permissions exist.
        $this->artisan('dash:install', $this->installOptions(['--admin-password' => 'weak']))
            ->expectsOutputToContain('The super admin could not be created')
            ->expectsOutputToContain('php artisan dash:install')
            ->assertFailed();

        $env = $this->envContents();
        $migrations = DB::table('migrations')->count();

        expect($this->databaseExists($this->database))->toBeTrue()
            ->and($migrations)->toBeGreaterThan(5)
            ->and(DB::table('permissions')->count())->toBeGreaterThan(10)
            ->and(Admin::query()->count())->toBe(0);

        // Same command, with the problem fixed.
        $this->artisan('dash:install', $this->installOptions())
            ->expectsOutputToContain('.env exists')
            ->expectsOutputToContain('Created super admin omar@example.com')
            ->assertSuccessful();

        expect($this->envContents())->toBe($env)
            ->and(DB::table('migrations')->count())->toBe($migrations)
            ->and(Admin::query()->firstOrFail()->isSuperAdmin())->toBeTrue();
    });

    it('recovers from a failed asset build', function (): void {
        $manifest = public_path('build/manifest.json');
        $backup = File::exists($manifest) ? File::get($manifest) : null;
        File::delete($manifest);

        try {
            Process::fake(['npm install' => Process::result(output: 'npm ERR! network', exitCode: 1)]);

            $this->artisan('dash:install', $this->installOptions(['--skip-assets' => false]))
                ->expectsOutputToContain('`npm install` failed')
                ->assertFailed();

            // Everything before the asset step is in place.
            expect(Admin::query()->count())->toBe(1);

            Process::fake();

            $this->artisan('dash:install', $this->installOptions(['--skip-assets' => false]))->assertSuccessful();

            expect(Admin::query()->count())->toBe(1);
        } finally {
            if ($backup !== null) {
                File::ensureDirectoryExists(dirname($manifest));
                File::put($manifest, $backup);
            }
        }
    })->skip(fn (): bool => (new ExecutableFinder)->find('npm') === null, 'npm is not installed');

    it('says which admin details are missing', function (): void {
        $this->artisan('dash:install', $this->installOptions(['--admin-name' => null, '--admin-email' => null, '--admin-password' => null]))
            ->expectsOutputToContain('--admin-name, --admin-email and --admin-password')
            ->assertFailed();
    });
});

describe('database problems', function (): void {
    it('explains a wrong password (1045)', function (): void {
        $this->artisan('dash:install', $this->installOptions(['--db-password' => 'definitely-not-the-password']))
            // One expectation per printed line: the hint and its advice.
            ->expectsOutputToContain('(error 1045)')
            ->expectsOutputToContain('A fresh local MySQL usually has user "root"')
            ->assertFailed();

        expect($this->databaseExists($this->database))->toBeFalse();
    });

    it('explains an unreachable server (2002)', function (): void {
        // Nothing listens on port 1 of the loopback address.
        $this->artisan('dash:install', $this->installOptions(['--db-host' => '127.0.0.1', '--db-port' => '1']))
            ->expectsOutputToContain('MySQL is not reachable at 127.0.0.1:1 (error 2002)')
            ->expectsOutputToContain('Is the server running')
            ->assertFailed();
    });

    it('explains a missing database (1049) and how to have it created', function (): void {
        $this->artisan('dash:install', $this->installOptions(['--create-db' => false]))
            ->expectsOutputToContain("The database '{$this->database}' does not exist (error 1049)")
            ->expectsOutputToContain('pass --create-db')
            ->assertFailed();

        expect($this->databaseExists($this->database))->toBeFalse();
    });

    it('uses an existing database without --create-db', function (): void {
        $this->pdo()->exec("CREATE DATABASE `{$this->database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $this->artisan('dash:install', $this->installOptions(['--create-db' => false, '--skip-testing-db' => true]))->assertSuccessful();

        expect(Admin::query()->count())->toBe(1);
    });

    it('refuses a database name that is not a plain identifier', function (): void {
        $this->artisan('dash:install', $this->installOptions(['--db-database' => 'x`; DROP DATABASE mysql; --']))->assertFailed();

        expect($this->databaseExists('mysql'))->toBeTrue();
    });
});

describe('demo data', function (): void {
    it('is refused outside the local environment before anything is touched', function (): void {
        // The suite runs with APP_ENV=testing.
        $this->artisan('dash:install', $this->installOptions(['--demo' => true]))
            ->expectsOutputToContain('Demo data is only available when APP_ENV=local')
            ->assertFailed();

        expect(File::exists($this->scratch.'/.env'))->toBeFalse()
            ->and($this->databaseExists($this->database))->toBeFalse();
    });

    it('is refused in production too', function (): void {
        app()->detectEnvironment(fn (): string => 'production');

        $this->artisan('dash:install', $this->installOptions(['--demo' => true]))->assertFailed();

        expect($this->databaseExists($this->database))->toBeFalse();
    });

    it('seeds limited admins and users in local, and only once', function (): void {
        app()->detectEnvironment(fn (): string => 'local');

        $this->artisan('dash:install', $this->installOptions(['--demo' => true]))->assertSuccessful();

        $support = Admin::query()->where('email', 'support@example.com')->firstOrFail();

        expect($support->hasRole('support'))->toBeTrue()
            ->and($support->can('view_any:user'))->toBeTrue()
            ->and($support->can('view_any:admin'))->toBeFalse()
            ->and($support->isSuperAdmin())->toBeFalse()
            ->and(User::query()->count())->toBe(25);

        $this->artisan('dash:install', $this->installOptions(['--demo' => true]))->assertSuccessful();

        expect(Admin::query()->count())->toBe(3)
            ->and(User::query()->count())->toBe(25);
    });
});

describe('in production with --no-interaction', function (): void {
    beforeEach(function (): void {
        // A deployed server: .env is in place and the database exists.
        $this->pdo()->exec("CREATE DATABASE `{$this->database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        File::put($this->scratch.'/.env', "APP_NAME=\"Deployed App\"\nAPP_ENV=production\nAPP_KEY=base64:".base64_encode(random_bytes(32))."\nAPP_URL=https://deployed.example.com\nDB_DATABASE={$this->database}\n");

        config([
            'app.name' => 'Deployed App',
            'app.url' => 'https://deployed.example.com',
            'database.connections.mysql.database' => $this->database,
        ]);
        DB::purge('mysql');

        app()->detectEnvironment(fn (): string => 'production');
    });

    it('keeps .env exactly as deployed', function (): void {
        $before = $this->envContents();

        $this->artisan('dash:install', $this->installOptions([
            '--app-name' => 'Should Be Ignored',
            '--db-database' => 'should_be_ignored',
        ]))->assertSuccessful();

        expect($this->envContents())->toBe($before)
            ->and($this->databaseExists('should_be_ignored'))->toBeFalse()
            ->and(Admin::query()->firstOrFail()->isSuperAdmin())->toBeTrue();
    });

    it('does not create a testing database, build assets or touch git', function (): void {
        Process::fake();

        $this->artisan('dash:install', $this->installOptions(['--skip-assets' => false, '--no-git' => false]))
            ->expectsOutputToContain('Skipped in production')
            ->assertSuccessful();

        Process::assertNothingRan();

        expect($this->databaseExists($this->database.'_testing'))->toBeFalse()
            ->and(File::exists($this->scratch.'/.env.testing'))->toBeFalse();
    });

    it('creates the testing database when asked', function (): void {
        $this->artisan('dash:install', $this->installOptions(['--testing-db' => true]))->assertSuccessful();

        expect($this->databaseExists($this->database.'_testing'))->toBeTrue();
    });

    it('writes no source files', function (): void {
        $snapshot = fn (): array => collect(File::allFiles(app_path()))
            ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getPathname() => $file->getMTime().':'.$file->getSize()])
            ->all();

        $before = $snapshot();

        $this->artisan('dash:install', $this->installOptions())->assertSuccessful();

        expect($snapshot())->toBe($before);
    });
});
