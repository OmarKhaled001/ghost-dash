<?php

declare(strict_types=1);

use App\Exceptions\GuardRailViolation;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use App\Services\RoleService;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

function roleService(): RoleService
{
    return resolve(RoleService::class);
}

const ROLE_MANAGER = ['view_any:role', 'view:role', 'create:role', 'update:role', 'delete:role'];

describe('the super admin role is fixed', function (): void {
    it('cannot be renamed or have its permissions changed, even by a super admin', function (): void {
        $actor = superAdmin();
        $role = Role::findByName(Role::SUPER_ADMIN, Role::GUARD);
        $before = $role->permissions()->count();

        expect(fn (): Role => roleService()->update($actor, $role, 'renamed', []))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.super_admin_role_is_fixed'));

        expect($role->fresh()->name)->toBe(Role::SUPER_ADMIN)
            ->and($role->permissions()->count())->toBe($before);
    });

    it('cannot be deleted', function (): void {
        superAdmin();
        $role = Role::findByName(Role::SUPER_ADMIN, Role::GUARD);

        expect(fn () => roleService()->delete($role))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.super_admin_role_is_fixed'));

        expect($role->fresh())->not->toBeNull();
    });

    it('is denied for update and delete in the policy', function (): void {
        $actor = superAdmin();
        $role = Role::findByName(Role::SUPER_ADMIN, Role::GUARD);

        expect($actor->can('view', $role))->toBeTrue()
            ->and($actor->can('update', $role))->toBeFalse()
            ->and($actor->can('delete', $role))->toBeFalse();
    });

    it('is invisible to admins who are not super admins', function (): void {
        superAdmin();
        $super = Role::findByName(Role::SUPER_ADMIN, Role::GUARD);
        $manager = actingAsAdmin(adminWith(ROLE_MANAGER));

        expect($manager->can('view', $super))->toBeFalse();

        livewire(ListRoles::class)->assertCanNotSeeTableRecords([$super]);

        get(RoleResource::getUrl('view', ['record' => $super]))->assertNotFound();
        get(RoleResource::getUrl('edit', ['record' => $super]))->assertNotFound();
    });
});

describe('editing roles cannot be used to escalate privileges', function (): void {
    it('refuses to grant a permission the actor does not hold', function (): void {
        $manager = adminWith(ROLE_MANAGER);

        expect(fn (): Role => roleService()->create($manager, 'backdoor', ['delete:admin']))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.cannot_grant_permissions_you_lack'));

        expect(Role::query()->where('name', 'backdoor')->exists())->toBeFalse();
    });

    it('refuses to add such a permission to the actor\'s own role', function (): void {
        $manager = adminWith(ROLE_MANAGER);
        $own = $manager->roles->first();

        expect(fn (): Role => roleService()->update($manager, $own, $own->name, [...ROLE_MANAGER, 'manage_backups']))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.cannot_grant_permissions_you_lack'));

        expect($manager->fresh()->can('manage_backups'))->toBeFalse();
    });

    it('lets the actor grant what they hold', function (): void {
        $manager = adminWith(ROLE_MANAGER);

        $role = roleService()->create($manager, 'role_viewer', ['view_any:role', 'view:role']);

        expect($role->permissions->pluck('name')->all())->toEqualCanonicalizing(['view_any:role', 'view:role']);
    });

    it('keeps permissions the actor lacks untouched when they edit a role', function (): void {
        $manager = adminWith(ROLE_MANAGER);
        $role = role('support', ['view_any:user', 'view_any:role']);

        // The form posts only what the manager ticked; view_any:user is
        // beyond them, so they can neither add nor remove it.
        roleService()->update($manager, $role, 'support', ['view:role']);

        expect($role->fresh()->permissions->pluck('name')->all())
            ->toEqualCanonicalizing(['view_any:user', 'view:role']);
    });

    it('places no such limit on a super admin', function (): void {
        $actor = superAdmin();

        $role = roleService()->create($actor, 'operator', ['manage_backups', 'view_health']);

        expect($role->permissions)->toHaveCount(2);
    });

    it('refuses permission names the application does not define', function (): void {
        $actor = superAdmin();

        expect(fn (): Role => roleService()->create($actor, 'odd', ['do_anything']))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.unknown_permission'));
    });

    it('reports a refusal in the panel instead of failing', function (): void {
        actingAsAdmin(adminWith(ROLE_MANAGER));

        livewire(CreateRole::class)
            ->fillForm(['name' => 'backdoor'])
            ->set('data.custom_permissions_tab', ['manage_backups'])
            ->call('create')
            ->assertNotified(__('admin.guard_rails.title'));

        expect(Role::query()->where('name', 'backdoor')->exists())->toBeFalse();
    });
});

describe('roles in use', function (): void {
    it('cannot be deleted while assigned to an admin', function (): void {
        $role = role('editor');
        admin(role: 'editor');

        expect(fn () => roleService()->delete($role))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.role_in_use'));

        expect($role->fresh())->not->toBeNull();
    });

    it('can be deleted once nobody holds them', function (): void {
        $role = role('editor');

        roleService()->delete($role);

        expect(Role::query()->whereKey($role->id)->exists())->toBeFalse();
    });
});

describe('the role form', function (): void {
    it('creates a role with the ticked permissions through the service', function (): void {
        actingAsAdmin(superAdmin());

        livewire(CreateRole::class)
            ->fillForm(['name' => 'operator'])
            ->set('data.custom_permissions_tab', ['manage_backups', 'view_health'])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::findByName('operator', Role::GUARD);

        expect($role->guard_name)->toBe('admin')
            ->and($role->permissions->pluck('name')->all())->toEqualCanonicalizing(['manage_backups', 'view_health']);
    });

    it('updates a role', function (): void {
        actingAsAdmin(superAdmin());
        $role = role('operator', ['manage_backups']);

        livewire(EditRole::class, ['record' => $role->getRouteKey()])
            ->fillForm(['name' => 'operations'])
            ->set('data.custom_permissions_tab', ['view_health'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($role->fresh()->name)->toBe('operations')
            ->and($role->fresh()->permissions->pluck('name')->all())->toBe(['view_health']);
    });

    it('requires a unique name', function (): void {
        actingAsAdmin(superAdmin());
        role('operator');

        livewire(CreateRole::class)
            ->fillForm(['name' => 'operator'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    });

    it('cannot create a second super admin role', function (): void {
        actingAsAdmin(superAdmin());

        livewire(CreateRole::class)
            ->fillForm(['name' => Role::SUPER_ADMIN])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    });
});
