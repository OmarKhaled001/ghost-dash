<?php

declare(strict_types=1);

use App\Filament\Pages\Backups;
use App\Jobs\CreateBackup;
use App\Models\Activity;
use App\Services\BackupService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    Storage::fake('backups');
    $this->admin = actingAsAdmin(adminWith(['manage_backups']));
});

function fakeBackup(string $name, string $contents = 'zip-bytes'): string
{
    $path = config('backup.backup.name').'/'.$name;
    Storage::disk('backups')->put($path, $contents);

    return $path;
}

it('lists the archives on the backup disk, newest first', function (): void {
    fakeBackup('2026-11-01-01-30-00.zip');
    fakeBackup('2026-11-03-01-30-00.zip');
    fakeBackup('2026-11-02-01-30-00.zip');

    expect(resolve(BackupService::class)->list()->pluck('name')->all())->toBe([
        '2026-11-03-01-30-00.zip',
        '2026-11-02-01-30-00.zip',
        '2026-11-01-01-30-00.zip',
    ]);

    livewire(Backups::class)
        ->assertOk()
        ->assertSee('2026-11-03-01-30-00.zip')
        ->assertSee('2026-11-01-01-30-00.zip');
});

it('shows an empty state when there are no backups', function (): void {
    livewire(Backups::class)->assertSee(__('admin.backups.empty.heading'));
});

it('queues a full backup', function (): void {
    Queue::fake();

    livewire(Backups::class)
        ->callAction('create')
        ->assertNotified(__('admin.backups.notifications.requested.title'));

    Queue::assertPushed(CreateBackup::class, fn (CreateBackup $job): bool => $job->onlyDatabase === false);
});

it('queues a database-only backup', function (): void {
    Queue::fake();

    livewire(Backups::class)->callAction('createDatabaseOnly');

    Queue::assertPushed(CreateBackup::class, fn (CreateBackup $job): bool => $job->onlyDatabase);
});

it('downloads an archive', function (): void {
    $path = fakeBackup('2026-11-01-01-30-00.zip', 'the-archive');

    livewire(Backups::class)
        ->callTableAction('download', $path)
        ->assertFileDownloaded('2026-11-01-01-30-00.zip');
});

it('deletes an archive', function (): void {
    $path = fakeBackup('2026-11-01-01-30-00.zip');

    livewire(Backups::class)
        ->callTableAction('delete', $path)
        ->assertNotified(__('admin.backups.notifications.deleted'));

    Storage::disk('backups')->assertMissing($path);
});

it('refuses a path that is not one of the listed backups', function (string $path): void {
    Storage::disk('backups')->put('secrets.txt', 'not a backup');
    Storage::disk('backups')->put(config('backup.backup.name').'/notes.txt', 'not a backup either');
    fakeBackup('2026-11-01-01-30-00.zip');

    expect(fn () => resolve(BackupService::class)->download($path))->toThrow(NotFoundHttpException::class);
    expect(fn () => resolve(BackupService::class)->delete($path))->toThrow(NotFoundHttpException::class);

    Storage::disk('backups')->assertExists('secrets.txt');
})->with([
    'a file outside the backup folder' => ['secrets.txt'],
    'path traversal' => ['app/../secrets.txt'],
    'a non-archive inside the folder' => ['app/notes.txt'],
    'an absolute path' => ['/etc/passwd'],
    'nothing' => [''],
]);

it('records who requested, downloaded and deleted a backup', function (): void {
    Queue::fake();
    $path = fakeBackup('2026-11-01-01-30-00.zip');
    $service = resolve(BackupService::class);

    $service->request(onlyDatabase: true);
    $service->download($path);
    $service->delete($path);

    $events = Activity::query()->where('log_name', 'backups')->orderBy('id')->get();

    expect($events->pluck('event')->all())->toBe(['requested', 'downloaded', 'deleted'])
        ->and($events->pluck('causer_id')->unique()->all())->toBe([$this->admin->id])
        ->and($events[1]->properties->get('file'))->toBe('2026-11-01-01-30-00.zip');
});

it('backs up to BACKUP_DISK and mails only when an address is configured', function (): void {
    expect(config('backup.backup.destination.disks'))->toBe([env('BACKUP_DISK', 'backups')])
        ->and(config('backup.backup.source.databases'))->toBe(['mysql'])
        ->and(config('backup.monitor_backups.0.disks'))->toBe(config('backup.backup.destination.disks'))
        // No BACKUP_NOTIFICATION_EMAIL in the test environment.
        ->and(collect(config('backup.notifications.notifications'))->flatten()->all())->toBe([]);
});

it('passes DB_DUMP_BINARY_PATH to the dumper', function (): void {
    expect(config('database.connections.mysql.dump'))->toHaveKeys(['dump_binary_path', 'use_single_transaction', 'timeout']);
});

it('schedules clean, run and monitor', function (string $command, string $expression): void {
    $event = collect(resolve(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, $command));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe($expression);
})->with([
    ['backup:clean', '0 1 * * *'],
    ['backup:run', '30 1 * * *'],
    ['backup:monitor', '0 3 * * *'],
]);

it('produces a real MySQL dump', function (): void {
    // The one test that runs mysqldump for real, against the testing database.
    Storage::forgetDisk('backups');
    config(['filesystems.disks.backups.root' => storage_path('framework/testing/backups-'.uniqid())]);

    try {
        resolve(BackupService::class)->run(onlyDatabase: true);

        $backups = resolve(BackupService::class)->list();
        expect($backups)->toHaveCount(1);

        $zip = new ZipArchive;
        $zip->open(Storage::disk('backups')->path($backups[0]['path']));
        $dump = $zip->getFromIndex(0);
        $entry = $zip->getNameIndex(0);
        $zip->close();

        expect($entry)->toStartWith('db-dumps/mysql-')
            ->and($dump)->toContain('CREATE TABLE `admins`')
            ->toContain('CREATE TABLE `activity_log`');
    } finally {
        File::deleteDirectory(config('filesystems.disks.backups.root'));
    }
})->skip(fn (): bool => blank(shell_exec('mysqldump --version 2>&1')) || ! str_contains((string) shell_exec('mysqldump --version 2>&1'), 'mysqldump'), 'mysqldump is not installed');
