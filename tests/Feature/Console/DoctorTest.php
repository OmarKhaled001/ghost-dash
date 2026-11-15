<?php

declare(strict_types=1);

use App\Data\DoctorResult;
use App\Enums\DoctorStatus;
use App\Services\DoctorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Facades\Health;

/**
 * @return array<string, DoctorResult>
 */
function doctor(): array
{
    return collect(resolve(DoctorService::class)->run())
        ->keyBy(fn (DoctorResult $result): string => $result->check)
        ->all();
}

function beatHeartbeats(): void
{
    $schedule = Health::registeredChecks()->first(fn ($check): bool => $check instanceof ScheduleCheck);
    $queue = Health::registeredChecks()->first(fn ($check): bool => $check instanceof QueueCheck);

    Cache::store($schedule->getCacheStoreName())->set($schedule->getCacheKey(), now()->timestamp);

    foreach ($queue->getQueues() as $name) {
        Cache::store($queue->getCacheStoreName())->set($queue->getHeartbeatCacheKey($name), now()->timestamp);
    }
}

it('runs every check the README lists', function (): void {
    expect(array_keys(doctor()))->toBe([
        'PHP version',
        'PHP extensions',
        'APP_KEY',
        'APP_DEBUG',
        'APP_ENV',
        'Host names',
        'Database connection',
        'MySQL version',
        'Scheduler',
        'Queue worker',
        'Telescope',
        'mysqldump',
        'Backup disk',
        'Storage link',
    ]);
});

it('passes the basics on a healthy installation', function (): void {
    $results = doctor();

    expect($results['PHP version']->status)->toBe(DoctorStatus::Pass)
        ->and($results['PHP extensions']->status)->toBe(DoctorStatus::Pass)
        ->and($results['APP_KEY']->status)->toBe(DoctorStatus::Pass)
        ->and($results['Database connection']->status)->toBe(DoctorStatus::Pass)
        ->and($results['Backup disk']->status)->toBe(DoctorStatus::Pass);
});

it('requires the extensions the README lists', function (): void {
    expect(DoctorService::EXTENSIONS)->toBe([
        'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'intl', 'mbstring',
        'openssl', 'pdo_mysql', 'tokenizer', 'xml', 'zip',
    ]);
});

it('fails on an empty APP_KEY', function (): void {
    config(['app.key' => '']);

    expect(doctor()['APP_KEY']->status)->toBe(DoctorStatus::Fail);
});

it('fails on debug mode in production and accepts it elsewhere', function (): void {
    config(['app.debug' => true]);
    expect(doctor()['APP_DEBUG']->status)->toBe(DoctorStatus::Pass);

    app()->detectEnvironment(fn (): string => 'production');
    expect(doctor()['APP_DEBUG']->status)->toBe(DoctorStatus::Fail);

    config(['app.debug' => false]);
    expect(doctor()['APP_DEBUG']->status)->toBe(DoctorStatus::Pass);
});

it('warns when a public-looking URL runs outside production', function (string $url, string $environment, DoctorStatus $expected): void {
    config(['app.url' => $url]);
    app()->detectEnvironment(fn (): string => $environment);

    expect(doctor()['APP_ENV']->status)->toBe($expected);
})->with([
    'localhost in local' => ['http://localhost:8000', 'local', DoctorStatus::Pass],
    'a .test domain in local' => ['http://my-app.test', 'local', DoctorStatus::Pass],
    'a public domain in local' => ['https://admin.example.com', 'local', DoctorStatus::Warn],
    'a public domain in staging' => ['https://staging.example.com', 'staging', DoctorStatus::Warn],
    'a public domain in production' => ['https://admin.example.com', 'production', DoctorStatus::Pass],
]);

it('warns when APP_URL is still a development address outside local', function (string $url, string $environment, DoctorStatus $expected): void {
    config(['app.url' => $url]);
    app()->detectEnvironment(fn (): string => $environment);

    expect(doctor()['Host names']->status)->toBe($expected);
})->with([
    'localhost in local' => ['http://localhost:8000', 'local', DoctorStatus::Pass],
    'localhost in production' => ['http://localhost:8000', 'production', DoctorStatus::Warn],
    'a .test domain in staging' => ['http://my-app.test', 'staging', DoctorStatus::Warn],
    'a public domain in production' => ['https://admin.example.com', 'production', DoctorStatus::Pass],
]);

it('fails when the database is unreachable', function (): void {
    config(['database.connections.mysql.port' => 1]);
    DB::purge('mysql');

    try {
        $results = doctor();
    } finally {
        config(['database.connections.mysql.port' => env('DB_PORT', 3306)]);
        DB::purge('mysql');
    }

    expect($results['Database connection']->status)->toBe(DoctorStatus::Fail);
});

it('treats missing heartbeats as warnings outside production', function (): void {
    $results = doctor();

    expect($results['Scheduler']->status)->toBe(DoctorStatus::Warn)
        ->and($results['Queue worker']->status)->toBe(DoctorStatus::Warn);
});

it('treats missing heartbeats as failures in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    $results = doctor();

    expect($results['Scheduler']->status)->toBe(DoctorStatus::Fail)
        ->and($results['Queue worker']->status)->toBe(DoctorStatus::Fail);
});

it('passes the heartbeat checks once the scheduler and a worker have been seen', function (): void {
    beatHeartbeats();

    $results = doctor();

    expect($results['Scheduler']->status)->toBe(DoctorStatus::Pass)
        ->and($results['Queue worker']->status)->toBe(DoctorStatus::Pass);
});

it('passes Telescope when disabled, and warns when it records outside local', function (): void {
    config(['telescope.enabled' => false]);
    expect(doctor()['Telescope']->status)->toBe(DoctorStatus::Pass);

    config(['telescope.enabled' => true]);
    app()->detectEnvironment(fn (): string => 'local');
    expect(doctor()['Telescope']->status)->toBe(DoctorStatus::Pass);

    app()->detectEnvironment(fn (): string => 'production');
    expect(doctor()['Telescope']->status)->toBe(DoctorStatus::Warn);
});

it('warns when mysqldump cannot be found', function (): void {
    config(['database.connections.mysql.dump.dump_binary_path' => '']);
    $path = getenv('PATH');
    putenv('PATH=/nonexistent');

    try {
        $status = doctor()['mysqldump']->status;
    } finally {
        putenv("PATH={$path}");
    }

    expect($status)->toBe(DoctorStatus::Warn);
});

it('fails when the backup disk does not work', function (): void {
    config(['backup.backup.destination.disks' => ['no-such-disk']]);

    expect(doctor()['Backup disk']->status)->toBe(DoctorStatus::Fail);
});

describe('the command', function (): void {
    it('exits 0 with warnings only', function (): void {
        Storage::fake('backups');

        $this->artisan('dash:doctor')
            ->expectsOutputToContain('PHP version')
            ->assertSuccessful();
    });

    it('exits 1 when any check fails', function (): void {
        config(['app.key' => '']);

        $this->artisan('dash:doctor')
            ->expectsOutputToContain('check(s) failed')
            ->assertFailed();
    });
});
