<?php

declare(strict_types=1);

use App\Enums\CustomPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionSyncService;

it('creates a permission for every resource action, widget and custom permission', function (): void {
    $result = resolve(PermissionSyncService::class)->sync();

    $names = Permission::query()->pluck('name')->all();

    expect($names)->toContain('view_any:admin', 'create:admin', 'update:role', 'delete_any:user', 'view:stats_overview')
        ->toContain(...CustomPermission::values())
        ->and($result['total'])->toBe(count($names))
        ->and($result['created'])->toHaveCount(count($names));
});

it('creates everything on the admin guard', function (): void {
    resolve(PermissionSyncService::class)->sync();

    expect(Permission::query()->where('guard_name', '!=', 'admin')->count())->toBe(0)
        ->and(Role::query()->where('guard_name', '!=', 'admin')->count())->toBe(0);
});

it('gives the super admin role every permission', function (): void {
    resolve(PermissionSyncService::class)->sync();

    $superAdmin = Role::findByName(Role::SUPER_ADMIN, Role::GUARD);

    expect($superAdmin->permissions()->count())->toBe(Permission::query()->count());
});

it('does not generate page permissions for pages gated by a custom permission', function (): void {
    resolve(PermissionSyncService::class)->sync();

    $names = Permission::query()->pluck('name');

    expect($names->filter(fn (string $name): bool => in_array($name, ['view:backups', 'view:health', 'view:manage_general_settings'], true))->all())->toBe([])
        ->and($names)->toContain('manage_backups', 'view_health', 'manage_settings');
});

it('is idempotent', function (): void {
    $service = resolve(PermissionSyncService::class);

    $service->sync();
    $permissions = Permission::query()->count();
    $second = $service->sync();

    expect($second['created'])->toBe([])
        ->and(Permission::query()->count())->toBe($permissions)
        ->and(Role::query()->where('name', Role::SUPER_ADMIN)->count())->toBe(1);
});

it('grants a permission added later to the existing super admin role', function (): void {
    $service = resolve(PermissionSyncService::class);
    $service->sync();

    // Simulate a deployment that introduces a permission: the row is missing
    // from the database and from the role.
    Permission::query()->where('name', 'view_health')->delete();

    $result = $service->sync();

    expect($result['created'])->toBe(['view_health'])
        ->and(Role::findByName(Role::SUPER_ADMIN, Role::GUARD)->hasPermissionTo('view_health'))->toBeTrue();
});

it('leaves other roles alone', function (): void {
    $editor = role('editor', ['view_any:user']);

    resolve(PermissionSyncService::class)->sync();

    expect($editor->fresh()->permissions->pluck('name')->all())->toBe(['view_any:user']);
});

it('defaults new roles and permissions to the admin guard', function (): void {
    expect(Role::create(['name' => 'plain'])->guard_name)->toBe('admin')
        ->and(Permission::create(['name' => 'plain'])->guard_name)->toBe('admin')
        ->and((new Role(['name' => 'unsaved']))->guard_name)->toBe('admin');
});
