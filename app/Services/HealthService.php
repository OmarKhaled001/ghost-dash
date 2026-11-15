<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResult;

final readonly class HealthService
{
    public function __construct(private ResultStore $results) {}

    /**
     * Results of the most recent run, in the order the checks are registered.
     *
     * @return Collection<int, array{name: string, label: string, status: string, summary: string, message: string}>
     */
    public function latest(): Collection
    {
        return collect($this->results->latestResults()->storedCheckResults ?? [])
            ->map(fn (StoredCheckResult $result): array => [
                'name' => $result->name,
                'label' => $result->label,
                'status' => $result->status,
                'summary' => $result->shortSummary,
                'message' => (string) $result->notificationMessage,
            ])
            ->values();
    }

    public function lastRanAt(): ?CarbonImmutable
    {
        $finishedAt = $this->results->latestResults()?->finishedAt;

        return $finishedAt instanceof DateTimeInterface ? CarbonImmutable::instance($finishedAt) : null;
    }

    /**
     * Run every check now and store the results.
     */
    public function run(): void
    {
        Artisan::call(RunHealthChecksCommand::class, ['--no-notification' => true]);
    }
}
