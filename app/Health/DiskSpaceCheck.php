<?php

declare(strict_types=1);

namespace App\Health;

use Override;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * Used disk space of the volume holding storage/.
 *
 * spatie/laravel-health ships UsedDiskSpaceCheck, which shells out to `df`.
 * That command does not exist on Windows, where the check would crash on
 * every run. PHP can answer the same question itself on every platform.
 */
final class DiskSpaceCheck extends Check
{
    private int $warningThreshold = 70;

    private int $errorThreshold = 90;

    public function warnWhenUsedSpaceIsAbovePercentage(int $percentage): self
    {
        $this->warningThreshold = $percentage;

        return $this;
    }

    public function failWhenUsedSpaceIsAbovePercentage(int $percentage): self
    {
        $this->errorThreshold = $percentage;

        return $this;
    }

    #[Override]
    public function run(): Result
    {
        $path = storage_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        $result = Result::make();

        if ($total === false || $free === false || $total <= 0.0) {
            return $result->failed('The free disk space could not be read.');
        }

        $used = (int) round(($total - $free) / $total * 100);

        $result
            ->meta(['disk_space_used_percentage' => $used])
            ->shortSummary($used.'%');

        return match (true) {
            $used > $this->errorThreshold => $result->failed("The disk is almost full ({$used}% used)."),
            $used > $this->warningThreshold => $result->warning("The disk is almost full ({$used}% used)."),
            default => $result->ok(),
        };
    }
}
