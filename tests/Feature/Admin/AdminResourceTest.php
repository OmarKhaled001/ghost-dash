<?php

declare(strict_types=1);

use App\Filament\Resources\Admins\AdminResource;
use App\Filament\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Resources\Admins\Pages\EditAdmin;
use App\Filament\Resources\Admins\Pages\ListAdmins;
use App\Filament\Resources\Admins\Pages\ViewAdmin;
use App\Models\Admin;
use Filament\Actions\DeleteAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->actor = actingAsAdmin(superAdmin());
});

it('lists admins without lazy loading their roles', function (): void {
    $roleQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        livewire(ListAdmins::class)->assertOk();

        return collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'model_has_roles'))
            ->count();
    };

    // The first render also loads the acting admin's own roles once.
    $roleQueries();

    admin();
    $withFewRows = $roleQueries();

    admin();
    admin();
    admin();
    $withMoreRows = $roleQueries();

    // Roles are eager loaded, so more rows must not mean more queries.
    expect($withMoreRows)->toBe($withFewRows);
});

it('compares permissions per row without a query per row', function (): void {
    // The comparison only happens for an actor who is not a super admin.
    actingAsAdmin(adminWith(['view_any:admin', 'view:admin', 'update:admin', 'delete:admin']));

    $permissionQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        livewire(ListAdmins::class)->assertOk();

        return collect(DB::getQueryLog())
            ->filter(fn (array $query): bool => str_contains($query['query'], 'role_has_permissions')
                || str_contains($query['query'], 'model_has_permissions'))
            ->count();
    };

    $permissionQueries();

    adminWith(['manage_backups']);
    $withFewRows = $permissionQueries();

    adminWith(['view_health']);
    admin();
    admin();
    $withMoreRows = $permissionQueries();

    expect($withMoreRows)->toBe($withFewRows);
});

it('searches by name and email', function (): void {
    $match = admin(['name' => 'Zainab Haddad', 'email' => 'zainab@example.com']);
    $other = admin(['name' => 'Someone Else']);

    livewire(ListAdmins::class)
        ->searchTable('Zainab')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other])
        ->searchTable('zainab@example.com')
        ->assertCanSeeTableRecords([$match]);
});

it('filters by status', function (): void {
    $active = admin();
    $inactive = admin(['is_active' => false]);

    livewire(ListAdmins::class)
        ->filterTable('is_active', false)
        ->assertCanSeeTableRecords([$inactive])
        ->assertCanNotSeeTableRecords([$active]);
});

it('creates an admin with roles, a hashed password and a verified address', function (): void {
    role('editor');

    livewire(CreateAdmin::class)
        ->fillForm([
            'name' => 'Layla Hassan',
            'email' => 'layla@example.com',
            'password' => 'A-very-strong-Passw0rd',
            'password_confirmation' => 'A-very-strong-Passw0rd',
            'roles' => ['editor'],
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $admin = Admin::query()->where('email', 'layla@example.com')->firstOrFail();

    expect($admin->name)->toBe('Layla Hassan')
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->hasRole('editor'))->toBeTrue()
        ->and($admin->password)->not->toBe('A-very-strong-Passw0rd')
        ->and(Hash::check('A-very-strong-Passw0rd', $admin->password))->toBeTrue();
});

it('validates the create form', function (array $input, array $errors): void {
    role('editor');
    admin(['email' => 'taken@example.com']);

    livewire(CreateAdmin::class)
        ->fillForm([
            'name' => 'Layla Hassan',
            'email' => 'layla@example.com',
            'password' => 'A-very-strong-Passw0rd',
            'password_confirmation' => 'A-very-strong-Passw0rd',
            'roles' => ['editor'],
            ...$input,
        ])
        ->call('create')
        ->assertHasFormErrors($errors);
})->with([
    'name is required' => [['name' => ''], ['name' => 'required']],
    'email must be an address' => [['email' => 'not-an-email'], ['email' => 'email']],
    'email must be unique' => [['email' => 'taken@example.com'], ['email' => 'unique']],
    'password follows the policy' => [['password' => 'short', 'password_confirmation' => 'short'], ['password']],
    'password must be confirmed' => [['password_confirmation' => 'Something-else-123'], ['password' => 'same']],
    'a role is required' => [['roles' => []], ['roles' => 'required']],
]);

it('rejects an SVG avatar, which could carry script', function (): void {
    Storage::fake('public');

    $admin = admin();

    livewire(EditAdmin::class, ['record' => $admin->getKey()])
        ->fillForm(['avatar_path' => UploadedFile::fake()->createWithContent('me.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
        ->call('save')
        ->assertHasFormErrors(['avatar_path']);

    livewire(EditAdmin::class, ['record' => $admin->getKey()])
        ->fillForm(['avatar_path' => UploadedFile::fake()->image('me.png', 128, 128)])
        ->call('save')
        ->assertHasNoFormErrors();

    Storage::disk('public')->assertExists((string) $admin->refresh()->avatar_path);
});

it('refuses an avatar path the browser made up', function (): void {
    Storage::fake('public');

    $admin = admin();

    livewire(EditAdmin::class, ['record' => $admin->getKey()])
        ->set('data.avatar_path', ['forged' => 'branding/logo.png'])
        ->call('save')
        ->assertHasFormErrors(['avatar_path']);

    expect($admin->refresh()->avatar_path)->toBeNull();
});

it('updates an admin and keeps the password when the field is left empty', function (): void {
    role('editor');
    role('auditor');
    $admin = admin(role: 'editor');
    $password = $admin->password;

    livewire(EditAdmin::class, ['record' => $admin->getRouteKey()])
        ->assertFormSet(['name' => $admin->name, 'roles' => ['editor']])
        ->fillForm(['name' => 'Renamed', 'roles' => ['auditor']])
        ->call('save')
        ->assertHasNoFormErrors();

    $admin->refresh();

    expect($admin->name)->toBe('Renamed')
        ->and($admin->password)->toBe($password)
        ->and($admin->getRoleNames()->all())->toBe(['auditor']);
});

it('changes the password when one is entered', function (): void {
    $admin = admin();

    livewire(EditAdmin::class, ['record' => $admin->getRouteKey()])
        ->fillForm([
            'password' => 'Another-strong-Passw0rd',
            'password_confirmation' => 'Another-strong-Passw0rd',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('Another-strong-Passw0rd', $admin->refresh()->password))->toBeTrue();
});

it('deactivates and reactivates an admin from the table', function (): void {
    $admin = admin();

    livewire(ListAdmins::class)
        ->callTableAction('deactivate', $admin)
        ->assertNotified(__('admin.admins.notifications.deactivated'));

    expect($admin->refresh()->is_active)->toBeFalse();

    livewire(ListAdmins::class)
        ->callTableAction('activate', $admin)
        ->assertNotified(__('admin.admins.notifications.activated'));

    expect($admin->refresh()->is_active)->toBeTrue();
});

it('deletes an admin from the table', function (): void {
    $admin = admin();

    livewire(ListAdmins::class)->callTableAction(DeleteAction::class, $admin);

    expect(Admin::query()->whereKey($admin->id)->exists())->toBeFalse();
});

it('shows last login details on the view page', function (): void {
    $admin = admin(['last_login_ip' => '203.0.113.9', 'last_login_at' => now()->subHour()]);

    livewire(ViewAdmin::class, ['record' => $admin->getRouteKey()])
        ->assertOk()
        ->assertSee('203.0.113.9');
});

it('hides write actions from an admin who may only view', function (): void {
    $viewer = actingAsAdmin(adminWith(['view_any:admin', 'view:admin']));
    $other = admin();

    livewire(ListAdmins::class)
        ->assertCanSeeTableRecords([$viewer, $other])
        ->assertTableActionHidden('edit', $other)
        ->assertTableActionHidden('deactivate', $other)
        ->assertTableActionHidden(DeleteAction::class, $other);

    $this->get(AdminResource::getUrl('create'))->assertForbidden();
    $this->get(AdminResource::getUrl('edit', ['record' => $other]))->assertForbidden();
});
