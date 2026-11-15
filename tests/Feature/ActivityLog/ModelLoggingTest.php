<?php

declare(strict_types=1);

use App\Actions\ActivityLog\RedactedLogActivityAction;
use App\Enums\ActivityLog;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminService;
use App\Services\RoleService;
use Illuminate\Support\Facades\Schema;

function lastActivity(?string $event = null): ?Activity
{
    return Activity::query()
        ->when($event, fn ($query) => $query->where('event', $event))
        ->latest('id')
        ->first();
}

describe('model events', function (): void {
    it('logs an admin being created, updated and deleted', function (): void {
        $admin = Admin::factory()->create(['name' => 'Before']);

        expect(lastActivity('created'))
            ->log_name->toBe(ActivityLog::Admins->value)
            ->subject_type->toBe('admin')
            ->subject_id->toBe($admin->id);

        $admin->update(['name' => 'After']);

        $updated = lastActivity('updated');
        expect($updated->oldValues())->toBe(['name' => 'Before'])
            ->and($updated->newValues())->toBe(['name' => 'After']);

        $admin->delete();

        expect(lastActivity('deleted')->subject_id)->toBe($admin->id);
    });

    it('logs users and roles in their own logs', function (): void {
        User::factory()->create();
        expect(lastActivity('created')->log_name)->toBe(ActivityLog::Users->value);

        Role::query()->create(['name' => 'editor']);
        expect(lastActivity('created')->log_name)->toBe(ActivityLog::Roles->value);
    });

    it('logs only what changed', function (): void {
        $admin = Admin::factory()->create();
        $admin->update(['name' => 'Renamed']);

        expect(array_keys(lastActivity('updated')->newValues()))->toBe(['name']);
    });

    it('does not log bookkeeping columns', function (): void {
        $admin = Admin::factory()->create();
        $before = Activity::query()->count();

        $admin->forceFill(['remember_token' => 'rotated', 'last_login_at' => now()])->save();

        expect(Activity::query()->count())->toBe($before);
    });

    it('records nothing for a save that changes nothing', function (): void {
        $admin = Admin::factory()->create();
        $before = Activity::query()->count();

        $admin->save();

        expect(Activity::query()->count())->toBe($before);
    });
});

describe('sensitive values', function (): void {
    it('shows that a password changed without storing either hash', function (): void {
        $admin = Admin::factory()->create();
        $oldHash = $admin->password;

        $admin->update(['password' => 'A-brand-new-Passw0rd']);

        $activity = lastActivity('updated');
        $stored = (string) $activity->getRawOriginal('attribute_changes');

        expect($activity->newValues())->toBe(['password' => RedactedLogActivityAction::MASK])
            ->and($activity->oldValues())->toBe(['password' => RedactedLogActivityAction::MASK])
            ->and($stored)->not->toContain($oldHash)
            ->not->toContain($admin->fresh()->password)
            ->not->toContain('A-brand-new-Passw0rd');
    });

    it('redacts the password on creation too', function (): void {
        $admin = Admin::factory()->create();

        expect(lastActivity('created')->newValues()['password'])->toBe(RedactedLogActivityAction::MASK)
            ->and((string) lastActivity('created')->getRawOriginal('attribute_changes'))->not->toContain($admin->password);
    });

    it('redacts sensitive keys in custom properties at any depth', function (): void {
        activity()->withProperties([
            'token' => 'plain-token',
            'ip' => '203.0.113.5',
            'request' => [
                'Authorization' => 'Bearer abc',
                'current_password' => 'hunter2',
                'nested' => ['app_authentication_secret' => 'JBSWY3DP', 'recovery_codes' => ['a', 'b']],
            ],
        ])->log('custom');

        $properties = lastActivity()->properties->all();

        expect($properties['token'])->toBe('[redacted]')
            ->and($properties['ip'])->toBe('203.0.113.5')
            ->and($properties['request']['Authorization'])->toBe('[redacted]')
            ->and($properties['request']['current_password'])->toBe('[redacted]')
            ->and($properties['request']['nested']['app_authentication_secret'])->toBe('[redacted]')
            ->and($properties['request']['nested']['recovery_codes'])->toBe('[redacted]');
    });

    it('keeps null as null so removal stays visible', function (): void {
        activity()->withProperties(['remember_token' => null])->log('custom');

        expect(lastActivity()->properties->all())->toBe(['remember_token' => null]);
    });

    it('never writes multi-factor secrets to the log', function (): void {
        $admin = Admin::factory()->create();
        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $admin->saveAppAuthenticationRecoveryCodes(['code-one', 'code-two']);

        $everything = Activity::query()->get()->map->getRawOriginal()->toJson();

        expect($everything)->not->toContain('JBSWY3DPEHPK3PXP')->not->toContain('code-one');
    });
});

describe('pivot changes', function (): void {
    it('logs roles attached to and detached from an admin', function (): void {
        role('editor');
        role('auditor');
        $admin = Admin::factory()->create();

        $admin->syncRoles(['editor']);

        expect(lastActivity('roles_synced'))
            ->subject_id->toBe($admin->id)
            ->log_name->toBe(ActivityLog::Admins->value)
            ->and(lastActivity('roles_synced')->properties->all())->toBe(['attached' => ['editor'], 'detached' => []]);

        $admin->syncRoles(['auditor']);

        expect(lastActivity('roles_synced')->properties->all())->toBe(['attached' => ['auditor'], 'detached' => ['editor']]);
    });

    it('does not log a role sync that changes nothing', function (): void {
        role('editor');
        $admin = Admin::factory()->create();
        $admin->syncRoles(['editor']);
        $before = Activity::query()->where('event', 'roles_synced')->count();

        $admin->syncRoles(['editor']);

        expect(Activity::query()->where('event', 'roles_synced')->count())->toBe($before);
    });

    it('logs role changes made through the admin service, with the acting admin as causer', function (): void {
        $actor = actingAsAdmin(superAdmin());
        role('editor');
        $admin = admin();

        resolve(AdminService::class)->update($actor, $admin, [], ['editor']);

        expect(lastActivity('roles_synced'))
            ->causer_type->toBe('admin')
            ->causer_id->toBe($actor->id)
            ->subject_id->toBe($admin->id);
    });

    it('logs permissions attached to and detached from a role', function (): void {
        syncPermissions();
        $role = Role::query()->create(['name' => 'operator']);

        $role->syncPermissions(['manage_backups', 'view_health']);

        expect(lastActivity('permissions_synced'))
            ->subject_type->toBe('role')
            ->subject_id->toBe($role->id)
            ->and(lastActivity('permissions_synced')->properties->get('attached'))->toEqualCanonicalizing(['manage_backups', 'view_health']);

        $role->syncPermissions(['view_health']);

        expect(lastActivity('permissions_synced')->properties->all())->toBe(['attached' => [], 'detached' => ['manage_backups']]);
    });

    it('logs exactly one entry per permission sync', function (): void {
        syncPermissions();
        $role = Role::query()->create(['name' => 'operator']);
        $before = Activity::query()->where('event', 'permissions_synced')->count();

        $role->syncPermissions(['manage_backups', 'view_health']);

        expect(Activity::query()->where('event', 'permissions_synced')->count())->toBe($before + 1);
    });

    it('logs permission changes made through the role service', function (): void {
        $actor = actingAsAdmin(superAdmin());

        $role = resolve(RoleService::class)->create($actor, 'operator', ['manage_backups']);

        expect(lastActivity('permissions_synced'))
            ->subject_id->toBe($role->id)
            ->causer_id->toBe($actor->id);
    });

    it('logs permissions granted to the super admin role by the sync', function (): void {
        syncPermissions();

        $granted = Activity::query()->where('event', 'permissions_synced')->latest('id')->first();

        expect($granted->properties->get('attached'))->toContain('view_any:admin', 'view_telescope');
    });
});

it('indexes the columns the panel filters and sorts by', function (): void {
    $indexed = collect(Schema::getIndexes('activity_log'))->map(fn (array $index): array => $index['columns']);

    expect($indexed)->toContain(['subject_type', 'subject_id'])
        ->toContain(['causer_type', 'causer_id'])
        ->toContain(['log_name'])
        ->toContain(['created_at']);
});
