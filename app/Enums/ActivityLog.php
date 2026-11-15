<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The log_name values the kit writes, so the panel can filter by them.
 */
enum ActivityLog: string
{
    case Admins = 'admins';
    case Users = 'users';
    case Roles = 'roles';
    case Authentication = 'authentication';
    case Settings = 'settings';
    case Backups = 'backups';

    public function label(): string
    {
        return __('admin.activity.logs.'.$this->value);
    }
}
