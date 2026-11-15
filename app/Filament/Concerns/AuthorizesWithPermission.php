<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Facades\Filament;

/**
 * For pages implementing GatedByPermission. Filament calls canAccess() both
 * to decide whether the page appears in the navigation and to abort with 403
 * when it is opened directly.
 */
trait AuthorizesWithPermission
{
    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can(static::gatePermission()) ?? false;
    }
}
