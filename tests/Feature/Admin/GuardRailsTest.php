<?php

declare(strict_types=1);

use App\Exceptions\GuardRailViolation;
use App\Filament\Resources\Admins\AdminResource;
use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Models\Admin;
use App\Models\Role;
use App\Repositories\Contracts\AdminRepository;
use App\Services\AdminService;
use Filament\Actions\DeleteAction;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

/*
| Each guard rail is asserted three times where it applies: in the service
| (the layer nothing can bypass), in the policy, and in the panel.
*/

function adminService(): AdminService
{
    return resolve(AdminService::class);
}

/**
 * Everything on admins except being a super admin.
 */
function adminManager(): Admin
{
    return adminWith(['view_any:admin', 'view:admin', 'create:admin', 'update:admin', 'delete:admin']);
}

describe('nobody can delete or deactivate themselves', function (): void {
    it('refuses self-deletion in the service', function (): void {
        superAdmin();
        $actor = superAdmin();

        expect(fn () => adminService()->delete($actor, $actor))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.cannot_delete_yourself'));

        expect($actor->fresh())->not->toBeNull();
    });

    it('refuses self-deactivation in the service', function (): void {
        superAdmin();
        $actor = superAdmin();

        expect(fn (): Admin => adminService()->deactivate($actor, $actor))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.cannot_deactivate_yourself'));

        expect($actor->fresh()->is_active)->toBeTrue();
    });

    it('refuses self-deactivation through a plain update as well', function (): void {
        superAdmin();
        $actor = superAdmin();

        expect(fn (): Admin => adminService()->update($actor, $actor, ['is_active' => false]))
            ->toThrow(GuardRailViolation::class);
    });

    it('denies both in the policy', function (): void {
        $actor = superAdmin();

        expect($actor->can('delete', $actor))->toBeFalse()
            ->and($actor->can('deactivate', $actor))->toBeFalse()
            ->and($actor->can('update', $actor))->toBeTrue();
    });

    it('does not offer either action on your own row', function (): void {
        $actor = actingAsAdmin(superAdmin());

        livewire(ListAdmins::class)
            ->assertTableActionHidden('deactivate', $actor)
            ->assertTableActionHidden(DeleteAction::class, $actor);
    });
});

describe('the last super admin cannot be removed or demoted', function (): void {
    it('refuses to delete the last active super admin', function (): void {
        $last = superAdmin();
        // A second super admin exists but is inactive, so cannot stand in.
        $inactive = superAdmin(['is_active' => false]);

        expect(fn () => adminService()->delete($inactive, $last))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.last_super_admin'));

        expect($last->fresh())->not->toBeNull();
    });

    it('refuses to deactivate the last active super admin', function (): void {
        $last = superAdmin();
        $inactive = superAdmin(['is_active' => false]);

        expect(fn (): Admin => adminService()->deactivate($inactive, $last))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.last_super_admin'));

        expect($last->fresh()->is_active)->toBeTrue();
    });

    it('refuses to demote the last active super admin, even at their own request', function (): void {
        $last = superAdmin();
        role('editor');

        expect(fn (): Admin => adminService()->update($last, $last, [], ['editor']))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.last_super_admin'));

        expect($last->fresh()->isSuperAdmin())->toBeTrue();
    });

    it('allows all three once another active super admin exists', function (): void {
        $first = superAdmin();
        $second = superAdmin();
        $third = superAdmin();
        role('editor');

        adminService()->update($first, $second, [], ['editor']);
        expect($second->fresh()->isSuperAdmin())->toBeFalse();

        adminService()->deactivate($first, $third);
        expect($third->fresh()->is_active)->toBeFalse();

        // $first is now the only active super admin again.
        expect(fn (): Admin => adminService()->update($first, $first, [], ['editor']))
            ->toThrow(GuardRailViolation::class);
    });

    it('reports the refusal in the panel instead of failing', function (): void {
        $last = actingAsAdmin(superAdmin());
        role('editor');

        livewire(EditAdmin::class, ['record' => $last->getRouteKey()])
            ->fillForm(['roles' => ['editor']])
            ->call('save')
            ->assertNotified(__('admin.guard_rails.title'));

        expect($last->fresh()->isSuperAdmin())->toBeTrue();
    });
});

describe('only a super admin can create, edit or assign super admin', function (): void {
    it('refuses to let a non-super admin create a super admin', function (): void {
        superAdmin();
        $manager = adminManager();

        expect(fn (): Admin => adminService()->create($manager, [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'A-very-strong-Passw0rd',
        ], [Role::SUPER_ADMIN]))->toThrow(GuardRailViolation::class, __('admin.guard_rails.super_admin_only'));

        expect(Admin::query()->where('email', 'mallory@example.com')->exists())->toBeFalse();
    });

    it('refuses to let a non-super admin promote anyone, themselves included', function (): void {
        superAdmin();
        $manager = adminManager();
        $colleague = admin();

        expect(fn (): Admin => adminService()->update($manager, $colleague, [], [Role::SUPER_ADMIN]))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.super_admin_only'));

        expect(fn (): Admin => adminService()->update($manager, $manager, [], [Role::SUPER_ADMIN]))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.super_admin_only'));

        expect($colleague->fresh()->isSuperAdmin())->toBeFalse()
            ->and($manager->fresh()->isSuperAdmin())->toBeFalse();
    });

    it('refuses to let a non-super admin edit, deactivate or delete a super admin', function (): void {
        $super = superAdmin();
        superAdmin();
        $manager = adminManager();

        expect(fn (): Admin => adminService()->update($manager, $super, ['name' => 'Renamed']))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.super_admin_only'));
        expect(fn (): Admin => adminService()->deactivate($manager, $super))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.super_admin_only'));
        expect(fn () => adminService()->delete($manager, $super))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.super_admin_only'));

        expect($super->fresh()->name)->not->toBe('Renamed');
    });

    it('denies the same in the policy', function (): void {
        $super = superAdmin();
        $manager = adminManager();

        expect($manager->can('view', $super))->toBeFalse()
            ->and($manager->can('update', $super))->toBeFalse()
            ->and($manager->can('deactivate', $super))->toBeFalse()
            ->and($manager->can('delete', $super))->toBeFalse();
    });

    it('does not offer the super admin role to a non-super admin', function (): void {
        superAdmin();
        actingAsAdmin(adminManager());

        livewire(CreateAdmin::class)
            ->assertFormFieldExists('roles', fn ($field): bool => ! array_key_exists(Role::SUPER_ADMIN, $field->getOptions()));
    });

    it('offers it to a super admin, who can assign it', function (): void {
        $actor = actingAsAdmin(superAdmin());

        livewire(CreateAdmin::class)
            ->assertFormFieldExists('roles', fn ($field): bool => array_key_exists(Role::SUPER_ADMIN, $field->getOptions()));

        $created = adminService()->create($actor, [
            'name' => 'Second',
            'email' => 'second@example.com',
            'password' => 'A-very-strong-Passw0rd',
        ], [Role::SUPER_ADMIN]);

        expect($created->isSuperAdmin())->toBeTrue();
    });

    it('rejects a forged super admin role posted through the form', function (): void {
        superAdmin();
        actingAsAdmin(adminManager());

        livewire(CreateAdmin::class)
            ->fillForm([
                'name' => 'Mallory',
                'email' => 'mallory@example.com',
                'password' => 'A-very-strong-Passw0rd',
                'password_confirmation' => 'A-very-strong-Passw0rd',
            ])
            ->set('data.roles', [Role::SUPER_ADMIN])
            ->call('create');

        expect(Admin::query()->where('email', 'mallory@example.com')->exists())->toBeFalse();
    });
});

describe('nobody hands out or takes over more access than they hold', function (): void {
    it('refuses to let an admin editor give themselves a role with permissions they lack', function (): void {
        role('operator', ['manage_backups', 'view_telescope']);
        $actor = adminManager();
        $own = $actor->roles->pluck('name')->all();

        expect(fn (): Admin => adminService()->update($actor, $actor, [], [...$own, 'operator']))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.cannot_grant_permissions_you_lack'));

        expect($actor->fresh()->can('manage_backups'))->toBeFalse();
    });

    it('refuses the same role on a new account or on someone else', function (): void {
        role('operator', ['manage_backups']);
        $actor = adminManager();
        $other = admin();

        expect(fn (): Admin => adminService()->create($actor, [
            'name' => 'Second Me',
            'email' => 'second@example.com',
            'password' => 'A-very-strong-Passw0rd',
        ], ['operator']))->toThrow(GuardRailViolation::class, __('admin.guard_rails.cannot_grant_permissions_you_lack'));

        expect(fn (): Admin => adminService()->update($actor, $other, [], ['operator']))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.cannot_grant_permissions_you_lack'));

        expect(Admin::query()->where('email', 'second@example.com')->exists())->toBeFalse()
            ->and($other->fresh()->hasRole('operator'))->toBeFalse();
    });

    it('lets them assign a role that stays within their own permissions', function (): void {
        role('reader', ['view_any:admin', 'view:admin']);
        $actor = adminManager();
        $other = admin();

        adminService()->update($actor, $other, [], ['reader']);

        expect($other->fresh()->hasRole('reader'))->toBeTrue();
    });

    it('refuses to edit, deactivate or delete an admin who holds permissions they lack', function (): void {
        $actor = adminManager();
        $operator = adminWith(['manage_backups']);

        expect(fn (): Admin => adminService()->update($actor, $operator, ['email' => 'mine-now@example.com', 'password' => 'A-very-strong-Passw0rd']))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.higher_authority'))
            ->and(fn (): Admin => adminService()->deactivate($actor, $operator))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.higher_authority'))
            ->and(fn () => adminService()->delete($actor, $operator))
            ->toThrow(GuardRailViolation::class, __('admin.guard_rails.higher_authority'));

        expect($operator->fresh())
            ->not->toBeNull()
            ->email->not->toBe('mine-now@example.com')
            ->is_active->toBeTrue();
    });

    it('denies the same in the policy, while still letting them look', function (): void {
        $actor = adminManager();
        $operator = adminWith(['manage_backups']);
        $peer = admin();

        expect($actor->can('view', $operator))->toBeTrue()
            ->and($actor->can('update', $operator))->toBeFalse()
            ->and($actor->can('deactivate', $operator))->toBeFalse()
            ->and($actor->can('delete', $operator))->toBeFalse()
            ->and($actor->can('update', $peer))->toBeTrue()
            ->and($actor->can('delete', $peer))->toBeTrue();
    });

    it('offers only the roles they could grant', function (): void {
        role('operator', ['manage_backups']);
        role('reader', ['view_any:admin']);
        actingAsAdmin(adminManager());

        livewire(CreateAdmin::class)
            ->assertFormFieldExists('roles', fn ($field): bool => array_key_exists('reader', $field->getOptions())
                && ! array_key_exists('operator', $field->getOptions()));
    });

    it('rejects a forged role posted through the form', function (): void {
        role('operator', ['manage_backups']);
        $actor = actingAsAdmin(adminManager());

        livewire(EditAdmin::class, ['record' => $actor->getKey()])
            ->set('data.roles', [...$actor->roles->pluck('name')->all(), 'operator'])
            ->call('save');

        expect($actor->fresh()->hasRole('operator'))->toBeFalse();
    });

    it('does not offer edit or delete on the row of an admin with more access', function (): void {
        actingAsAdmin(adminManager());
        $operator = adminWith(['manage_backups']);

        livewire(ListAdmins::class)
            ->assertCanSeeTableRecords([$operator])
            ->assertTableActionHidden('edit', $operator)
            ->assertTableActionHidden(DeleteAction::class, $operator);
    });
});

describe('non-super admins cannot see super admins', function (): void {
    it('leaves super admins out of the repository query', function (): void {
        $super = superAdmin();
        $manager = adminManager();

        $visible = resolve(AdminRepository::class)->queryVisibleTo($manager)->pluck('id');

        expect($visible)->toContain($manager->id)->not->toContain($super->id);
    });

    it('includes them for a super admin', function (): void {
        $super = superAdmin();
        $other = superAdmin();

        $visible = resolve(AdminRepository::class)->queryVisibleTo($super)->pluck('id');

        expect($visible)->toContain($other->id);
    });

    it('leaves super admins out of the table', function (): void {
        $super = superAdmin();
        $manager = actingAsAdmin(adminManager());
        $colleague = admin();

        livewire(ListAdmins::class)
            ->assertCanSeeTableRecords([$manager, $colleague])
            ->assertCanNotSeeTableRecords([$super]);
    });

    it('answers 404 when a super admin is requested by URL', function (): void {
        $super = superAdmin();
        actingAsAdmin(adminManager());

        get(AdminResource::getUrl('view', ['record' => $super]))->assertNotFound();
        get(AdminResource::getUrl('edit', ['record' => $super]))->assertNotFound();
    });
});

describe('role assignment is validated', function (): void {
    it('refuses a role that does not exist', function (): void {
        $actor = superAdmin();

        expect(fn (): Admin => adminService()->create($actor, [
            'name' => 'Nobody',
            'email' => 'nobody@example.com',
            'password' => 'A-very-strong-Passw0rd',
        ], ['no_such_role']))->toThrow(GuardRailViolation::class, __('admin.guard_rails.unknown_role'));
    });

    it('rolls the admin back when assigning roles fails', function (): void {
        $actor = superAdmin();

        try {
            adminService()->create($actor, [
                'name' => 'Nobody',
                'email' => 'nobody@example.com',
                'password' => 'A-very-strong-Passw0rd',
            ], ['no_such_role']);
        } catch (GuardRailViolation) {
            // expected
        }

        expect(Admin::query()->where('email', 'nobody@example.com')->exists())->toBeFalse();
    });
});
