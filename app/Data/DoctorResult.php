<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DoctorStatus;

final readonly class DoctorResult
{
    public function __construct(
        public string $check,
        public DoctorStatus $status,
        public string $message,
    ) {}

    public static function pass(string $check, string $message): self
    {
        return new self($check, DoctorStatus::Pass, $message);
    }

    public static function warn(string $check, string $message): self
    {
        return new self($check, DoctorStatus::Warn, $message);
    }

    public static function fail(string $check, string $message): self
    {
        return new self($check, DoctorStatus::Fail, $message);
    }
}
