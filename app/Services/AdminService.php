<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GuardRailViolation;
use App\Models\Admin;
use App\Models\Role;
use App\Repositories\Contracts\AdminRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Every write to an admin account goes through this class, which is where
 * the access-control guard rails live. Policies repeat the same rules so the
 * panel can hide what would fail, but this is the layer that cannot be
 * skipped by a new page, a console command or a queued job.
 */
final readonly class AdminService
{
    private const array WRITABLE = ['name', 'email', 'password', 'avatar_path', 'is_active'];

    public function __construct(private AdminRepository $admins) {}

    /**
     * @param  array{name: string, email: string, password: string, avatar_path?: string|null, is_active?: bool}  $attributes
     * @param  list<string>  $roles
     */
    public function create(Admin $actor, array $attributes, array $roles): Admin
    {
        $this->ensureMayAssign($actor, $roles);

        return DB::transaction(function () use ($attributes, $roles): Admin {
            $admin = $this->admins->create([
                ...Arr::only($attributes, self::WRITABLE),
                // Whoever creates the account vouches for the address. The
                // admin still has to verify any address they change to later.
                'email_verified_at' => now(),
            ]);

            $admin->syncRoles($roles);

            return $admin;
        });
    }

    /**
     * Creates the first super admin during installation, when there is no
     * actor yet to authorise the write.
     *
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function createFirstSuperAdmin(array $attributes): Admin
    {
        return DB::transaction(function () use ($attributes): Admin {
            $admin = $this->admins->create([
                ...Arr::only($attributes, ['name', 'email', 'password']),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $admin->syncRoles([Role::SUPER_ADMIN]);

            return $admin;
        });
    }

    /**
     * @param  array{name?: string, email?: string, password?: string|null, avatar_path?: string|null, is_active?: bool}  $attributes
     * @param  list<string>|null  $roles  Null leaves the roles untouched.
     */
    public function update(Admin $actor, Admin $admin, array $attributes, ?array $roles = null): Admin
    {
        $this->ensureMayManage($actor, $admin);

        if ($roles !== null) {
            $this->ensureMayAssign($actor, $roles);
        }

        $attributes = Arr::only($attributes, self::WRITABLE);

        // An empty password field on the edit form means "keep the current one".
        if (blank($attributes['password'] ?? null)) {
            unset($attributes['password']);
        }

        return DB::transaction(function () use ($actor, $admin, $attributes, $roles): Admin {
            $deactivating = $admin->is_active && ($attributes['is_active'] ?? true) === false;
            $demoting = $roles !== null && $admin->isSuperAdmin() && ! in_array(Role::SUPER_ADMIN, $roles, true);

            if ($deactivating && $actor->is($admin)) {
                throw GuardRailViolation::cannotDeactivateYourself();
            }

            if ($deactivating || $demoting) {
                $this->ensureNotLastSuperAdmin($admin);
            }

            $admin = $this->admins->update($admin, $attributes);

            if ($roles !== null) {
                $admin->syncRoles($roles);
            }

            return $admin;
        });
    }

    public function activate(Admin $actor, Admin $admin): Admin
    {
        return $this->update($actor, $admin, ['is_active' => true]);
    }

    public function deactivate(Admin $actor, Admin $admin): Admin
    {
        return $this->update($actor, $admin, ['is_active' => false]);
    }

    public function delete(Admin $actor, Admin $admin): void
    {
        if ($actor->is($admin)) {
            throw GuardRailViolation::cannotDeleteYourself();
        }

        $this->ensureMayManage($actor, $admin);

        DB::transaction(function () use ($admin): void {
            $this->ensureNotLastSuperAdmin($admin);

            $this->admins->delete($admin);
        });
    }

    /**
     * Only a super admin may touch a super admin account, and nobody may
     * touch an account that can do more than their own: changing its email
     * or password would be a way to take it over.
     */
    private function ensureMayManage(Admin $actor, Admin $admin): void
    {
        if ($admin->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            throw GuardRailViolation::superAdminOnly();
        }

        if (! $actor->hasAuthorityOver($admin)) {
            throw GuardRailViolation::higherAuthority();
        }
    }

    /**
     * @param  list<string>  $roles
     */
    private function ensureMayAssign(Admin $actor, array $roles): void
    {
        if (in_array(Role::SUPER_ADMIN, $roles, true) && ! $actor->isSuperAdmin()) {
            throw GuardRailViolation::superAdminOnly();
        }

        $found = Role::query()
            ->where('guard_name', Role::GUARD)
            ->whereIn('name', $roles)
            ->with('permissions')
            ->get();

        if ($found->count() !== count(array_unique($roles))) {
            throw GuardRailViolation::unknownRole();
        }

        // Otherwise "may edit admins" would mean "may have every role":
        // an admin could simply assign the others to themselves.
        if ($found->contains(fn (Role $role): bool => ! $actor->mayGrant($role))) {
            throw GuardRailViolation::cannotGrantPermissionsYouLack();
        }
    }

    /**
     * Taking the last active super admin out of action would leave nobody
     * able to manage roles, and no way back in short of editing the database.
     */
    private function ensureNotLastSuperAdmin(Admin $admin): void
    {
        if (! $admin->is_active || ! $admin->isSuperAdmin()) {
            return;
        }

        if ($this->admins->countOtherActiveSuperAdminsForUpdate($admin) === 0) {
            throw GuardRailViolation::lastSuperAdmin();
        }
    }
}
