<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\DoctorResult;
use App\Enums\DoctorStatus;
use App\Services\DoctorService;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'dash:doctor', description: 'Check that this GhostDash installation is set up correctly')]
#[Signature('dash:doctor')]
final class DashDoctorCommand extends Command
{
    public function handle(DoctorService $doctor): int
    {
        $results = $doctor->run();

        $this->newLine();

        foreach ($results as $result) {
            $this->line(sprintf('  %s  <options=bold>%s</>  %s', $this->badge($result->status), $result->check, $result->message));
        }

        $failed = count(array_filter($results, fn (DoctorResult $result): bool => $result->status === DoctorStatus::Fail));
        $warned = count(array_filter($results, fn (DoctorResult $result): bool => $result->status === DoctorStatus::Warn));

        $this->newLine();

        if ($failed > 0) {
            $this->components->error("{$failed} check(s) failed, {$warned} warning(s).");

            return self::FAILURE;
        }

        $warned > 0
            ? $this->components->warn("No failures, {$warned} warning(s).")
            : $this->components->info('Everything looks good.');

        return self::SUCCESS;
    }

    private function badge(DoctorStatus $status): string
    {
        return match ($status) {
            DoctorStatus::Pass => '<fg=green>PASS</>',
            DoctorStatus::Warn => '<fg=yellow>WARN</>',
            DoctorStatus::Fail => '<fg=red>FAIL</>',
        };
    }
}
