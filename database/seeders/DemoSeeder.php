<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionSyncService;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Sample data for looking around a fresh local install: two limited roles,
 * an admin for each, and some end users. Never run outside local: every
 * demo account has the password "password".
 */
final class DemoSeeder extends Seeder
{
    public const string PASSWORD = 'password';

    /**
     * Role name to the permissions it holds.
     */
    private const array ROLES = [
        'support' => ['view_any:user', 'view:user', 'update:user'],
        'auditor' => ['view_any:user', 'view:user', 'view_any:admin', 'view:admin', 'view_activity_log', 'view_health'],
    ];

    public function run(PermissionSyncService $permissions): void
    {
        if (! app()->environment('local')) {
            throw new LogicException('Demo data is only for the local environment.');
        }

        $permissions->sync();

        foreach (self::ROLES as $name => $granted) {
            $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => Role::GUARD]);
            $role->syncPermissions($granted);

            $admin = Admin::query()->firstOrCreate(
                ['email' => "{$name}@example.com"],
                [
                    'name' => ucfirst($name).' Demo',
                    'password' => self::PASSWORD,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $admin->syncRoles([$name]);
        }

        // firstOrCreate above keeps a second run from duplicating admins;
        // users are topped up to a fixed number for the same reason.
        $missing = max(0, 25 - User::query()->count());

        User::factory()->count($missing)->create();
    }
}
