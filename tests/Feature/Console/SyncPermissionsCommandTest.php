<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

it('creates the permissions and reports them', function (): void {
    $this->artisan('dash:sync-permissions')
        ->expectsOutputToContain('view_telescope')
        ->assertSuccessful();

    expect(Permission::query()->where('guard_name', 'admin')->count())->toBeGreaterThan(20)
        ->and(Role::findByName(Role::SUPER_ADMIN, Role::GUARD)->permissions()->count())->toBe(Permission::query()->count());
});

it('is idempotent', function (): void {
    $this->artisan('dash:sync-permissions')->assertSuccessful();
    $count = Permission::query()->count();

    $this->artisan('dash:sync-permissions')
        ->expectsOutputToContain('0 new')
        ->assertSuccessful();

    expect(Permission::query()->count())->toBe($count);
});

it('makes a super admin able to use a permission that was just added', function (): void {
    $admin = superAdmin();
    Permission::query()->where('name', 'view_health')->delete();
    resolve(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($admin->fresh()->can('view_health'))->toBeFalse();

    $this->artisan('dash:sync-permissions')->assertSuccessful();

    // No stale cache in the way: the command resets it.
    expect($admin->fresh()->can('view_health'))->toBeTrue();
});

it('writes no files, so it is safe on a production server', function (): void {
    $snapshot = fn (): array => collect(File::allFiles(app_path()))
        ->mapWithKeys(fn (SplFileInfo $file): array => [$file->getPathname() => $file->getMTime().':'.$file->getSize()])
        ->all();

    $before = $snapshot();

    app()->detectEnvironment(fn (): string => 'production');
    $this->artisan('dash:sync-permissions')->assertSuccessful();

    expect($snapshot())->toBe($before);
});
