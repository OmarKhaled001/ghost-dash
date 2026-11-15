<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Permissions for features that are not Filament resources, so Shield has
 * nothing to derive them from.
 */
enum CustomPermission: string
{
    case ViewTelescope = 'view_telescope';
    case ViewActivityLog = 'view_activity_log';
    case ManageSettings = 'manage_settings';
    case ManageBackups = 'manage_backups';
    case ViewHealth = 'view_health';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return __('admin.permissions.'.$this->value);
    }
}
