<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GuardRailViolation;
use App\Models\Admin;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

final readonly class RoleService
{
    public function __construct(private PermissionSyncService $permissions) {}

    /**
     * @param  list<string>  $permissions
     */
    public function create(Admin $actor, string $name, array $permissions): Role
    {
        $permissions = $this->grantable($actor, $permissions, current: []);

        return DB::transaction(function () use ($name, $permissions): Role {
            $role = Role::query()->create(['name' => $name, 'guard_name' => Role::GUARD]);
            $role->syncPermissions($this->models($permissions));

            return $role;
        });
    }

    /**
     * @param  list<string>  $permissions
     */
    public function update(Admin $actor, Role $role, string $name, array $permissions): Role
    {
        // super_admin always holds every permission (dash:sync-permissions
        // keeps it that way) and the rest of the kit looks it up by name,
        // so neither its name nor its permissions are editable.
        if ($role->isSuperAdmin()) {
            throw GuardRailViolation::superAdminRoleIsFixed();
        }

        $permissions = $this->grantable($actor, $permissions, current: $role->permissions->pluck('name')->all());

        return DB::transaction(function () use ($role, $name, $permissions): Role {
            $role->update(['name' => $name]);
            $role->syncPermissions($this->models($permissions));

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        if ($role->isSuperAdmin()) {
            throw GuardRailViolation::superAdminRoleIsFixed();
        }

        DB::transaction(function () use ($role): void {
            // Deleting a role that is still assigned would silently strip
            // access from those admins, and lock out any whose only role it is.
            if ($role->users()->lockForUpdate()->exists()) {
                throw GuardRailViolation::roleInUse();
            }

            $role->delete();
        });
    }

    /**
     * Work out the permission set an actor is allowed to leave a role with.
     *
     * Someone who can edit roles must not be able to use that to hand out
     * permissions they do not hold themselves, which would make "can edit
     * roles" equal to "is super admin". Permissions the actor lacks are
     * therefore frozen: they stay if the role already has them, and cannot
     * be added if it does not.
     *
     * @param  list<string>  $requested
     * @param  list<string>  $current
     * @return list<string>
     */
    private function grantable(Admin $actor, array $requested, array $current): array
    {
        $requested = array_values(array_unique($requested));

        if (array_diff($requested, $this->permissions->known()) !== []) {
            throw GuardRailViolation::unknownPermission();
        }

        if ($actor->isSuperAdmin()) {
            return $requested;
        }

        $held = $actor->getAllPermissions()->pluck('name')->all();
        $beyondActor = array_diff($requested, $held);

        if (array_diff($beyondActor, $current) !== []) {
            throw GuardRailViolation::cannotGrantPermissionsYouLack();
        }

        $frozen = array_diff($current, $held);

        return array_values(array_unique([...array_intersect($requested, $held), ...$frozen]));
    }

    /**
     * @param  list<string>  $names
     * @return list<Permission>
     */
    private function models(array $names): array
    {
        return array_map(
            fn (string $name): Permission => Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => Role::GUARD]),
            $names,
        );
    }
}
