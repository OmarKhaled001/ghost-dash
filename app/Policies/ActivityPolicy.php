<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CustomPermission;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * The activity log is an audit trail: it can be read by those holding
 * view_activity_log and changed by nobody, super admins included.
 */
final class ActivityPolicy
{
    public function viewAny(AuthUser $actor): bool
    {
        return $actor->can(CustomPermission::ViewActivityLog->value);
    }

    public function view(AuthUser $actor): bool
    {
        return $actor->can(CustomPermission::ViewActivityLog->value);
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }

    public function deleteAny(): bool
    {
        return false;
    }
}
