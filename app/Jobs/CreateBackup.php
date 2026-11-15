<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;

#[Timeout(900)]
#[Tries(1)]
final class CreateBackup implements ShouldQueue
{
    use Queueable;

    public function __construct(public bool $onlyDatabase = false) {}

    public function handle(BackupService $backups): void
    {
        $backups->run($this->onlyDatabase);
    }
}
