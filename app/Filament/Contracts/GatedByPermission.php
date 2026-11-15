<?php

declare(strict_types=1);

namespace App\Filament\Contracts;

/**
 * A page or resource whose access is decided by one named permission that
 * Shield does not generate for it (the custom permissions, mostly).
 *
 * The authorization test suite discovers every registered page and resource
 * and uses this to know which permission to grant, so a new gated page is
 * covered without editing a test.
 */
interface GatedByPermission
{
    public static function gatePermission(): string;
}
