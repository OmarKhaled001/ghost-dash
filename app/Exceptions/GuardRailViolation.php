<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by services when a write would break one of the access-control
 * invariants. The message is translated and safe to show to the admin.
 */
final class GuardRailViolation extends DomainException
{
    public static function cannotDeleteYourself(): self
    {
        return new self(__('admin.guard_rails.cannot_delete_yourself'));
    }

    public static function cannotDeactivateYourself(): self
    {
        return new self(__('admin.guard_rails.cannot_deactivate_yourself'));
    }

    public static function lastSuperAdmin(): self
    {
        return new self(__('admin.guard_rails.last_super_admin'));
    }

    public static function superAdminOnly(): self
    {
        return new self(__('admin.guard_rails.super_admin_only'));
    }

    public static function superAdminRoleIsFixed(): self
    {
        return new self(__('admin.guard_rails.super_admin_role_is_fixed'));
    }

    public static function roleInUse(): self
    {
        return new self(__('admin.guard_rails.role_in_use'));
    }

    public static function cannotGrantPermissionsYouLack(): self
    {
        return new self(__('admin.guard_rails.cannot_grant_permissions_you_lack'));
    }

    public static function higherAuthority(): self
    {
        return new self(__('admin.guard_rails.higher_authority'));
    }

    public static function unknownRole(): self
    {
        return new self(__('admin.guard_rails.unknown_role'));
    }

    public static function unknownPermission(): self
    {
        return new self(__('admin.guard_rails.unknown_permission'));
    }
}
