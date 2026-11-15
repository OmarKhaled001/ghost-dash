<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityLog;
use App\Jobs\CreateBackup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lists, creates, downloads and deletes backup archives on BACKUP_DISK.
 *
 * Every method that takes a path first resolves it against the list of
 * archives spatie/laravel-backup itself reports. A path that is not in that
 * list is refused, so a crafted value cannot reach other files on the disk.
 */
final class BackupService
{
    public function disk(): string
    {
        return (string) (config('backup.backup.destination.disks')[0] ?? 'backups');
    }

    /**
     * Newest first.
     *
     * @return Collection<int, array{path: string, name: string, size: int, created_at: CarbonImmutable}>
     */
    public function list(): Collection
    {
        return collect($this->destination()->backups()->all())
            ->map(fn (Backup $backup): array => [
                'path' => $backup->path(),
                'name' => basename($backup->path()),
                'size' => (int) $backup->sizeInBytes(),
                'created_at' => CarbonImmutable::instance($backup->date()),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    public function isWritable(): bool
    {
        return $this->destination()->isReachable();
    }

    /**
     * Queue a backup. Dumping a database inside a web request would hold
     * the request open for as long as the dump takes.
     */
    public function request(bool $onlyDatabase = false): void
    {
        dispatch(new CreateBackup($onlyDatabase));

        $this->log('requested', ['only_database' => $onlyDatabase]);
    }

    /**
     * Run a backup now, in this process.
     */
    public function run(bool $onlyDatabase = false): void
    {
        $exitCode = Artisan::call('backup:run', $onlyDatabase ? ['--only-db' => true] : []);

        if ($exitCode !== 0) {
            throw new RuntimeException('The backup failed: '.trim(Artisan::output()));
        }
    }

    public function download(string $path): StreamedResponse
    {
        $backup = $this->find($path);

        $this->log('downloaded', ['file' => basename($backup->path())]);

        return $backup->disk()->download($backup->path());
    }

    public function delete(string $path): void
    {
        $backup = $this->find($path);
        $name = basename($backup->path());

        $backup->delete();

        $this->log('deleted', ['file' => $name]);
    }

    private function find(string $path): Backup
    {
        $backup = $this->destination()
            ->backups()
            ->first(fn (Backup $backup): bool => $backup->path() === $path);

        abort_if($backup === null, 404);

        return $backup;
    }

    private function destination(): BackupDestination
    {
        return BackupDestination::create($this->disk(), (string) config('backup.backup.name'));
    }

    /**
     * A database dump is the most sensitive file the application produces,
     * so who asked for, took or removed one is always recorded.
     *
     * @param  array<string, mixed>  $properties
     */
    private function log(string $event, array $properties): void
    {
        activity(ActivityLog::Backups)
            ->event($event)
            ->withProperties($properties)
            ->log($event);
    }
}
