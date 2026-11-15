<?php

declare(strict_types=1);

namespace App\Enums;

enum DoctorStatus: string
{
    case Pass = 'pass';
    case Warn = 'warn';
    case Fail = 'fail';
}
