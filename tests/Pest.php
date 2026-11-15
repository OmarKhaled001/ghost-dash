<?php

declare(strict_types=1);

use App\Enums\TokenAbility;
use App\Models\Admin;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionSyncService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the application and run against the MySQL testing
| database. LazilyRefreshDatabase migrates once per process and wraps each
| test that actually touches the database in a transaction.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
| The installer tests create and drop databases of their own and switch the
| connection to them. They must not use a database trait: its transaction
| and its "already migrated" bookkeeping belong to the shared test database.
*/
pest()->extend(TestCase::class)->in('Installer');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create the permission rows and the super_admin role, as dash:sync-permissions does.
 */
function syncPermissions(): void
{
    resolve(PermissionSyncService::class)->sync();
}

/**
 * @param  list<string>  $permissions
 */
function role(string $name, array $permissions = []): Role
{
    if ($permissions !== []) {
        syncPermissions();
    }

    $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => Role::GUARD]);
    $role->syncPermissions($permissions);

    return $role;
}

/**
 * An active, verified admin holding a role without any permission: enough
 * to enter the panel, not enough to do anything in it.
 *
 * @param  array<string, mixed>  $attributes
 */
function admin(array $attributes = [], ?string $role = 'member'): Admin
{
    $admin = Admin::factory()->create($attributes);

    if ($role !== null) {
        $admin->assignRole(role($role));
    }

    // Re-read the row so the instance carries every column. A freshly
    // created model only knows the attributes the factory set, and strict
    // mode rightly objects when code reads one of the others.
    return $admin->refresh();
}

/**
 * An admin whose only role grants exactly the given permissions.
 *
 * @param  list<string>  $permissions
 * @param  array<string, mixed>  $attributes
 */
function adminWith(array $permissions, array $attributes = []): Admin
{
    $admin = Admin::factory()->create($attributes);
    $admin->assignRole(role('role_'.uniqid(), $permissions));

    return $admin->refresh();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function superAdmin(array $attributes = []): Admin
{
    syncPermissions();

    $admin = Admin::factory()->create($attributes);
    $admin->assignRole(Role::SUPER_ADMIN);

    return $admin->refresh();
}

function actingAsAdmin(?Admin $admin = null): Admin
{
    $admin ??= admin();

    test()->actingAs($admin, 'admin');

    return $admin;
}

function panelUrl(string $path = ''): string
{
    return '/'.config('ghostdash.panel.path').($path === '' ? '' : '/'.ltrim($path, '/'));
}

/*
|--------------------------------------------------------------------------
| API helpers
|--------------------------------------------------------------------------
*/

/**
 * A bearer token for the user, as the login endpoint would issue it.
 *
 * @param  list<string>|null  $abilities
 */
function tokenFor(User $user, ?array $abilities = null): string
{
    return $user->createToken('test', $abilities ?? TokenAbility::values(), now()->addDay())->plainTextToken;
}

/**
 * Laravel keeps the authenticated user on the guard for the lifetime of the
 * application instance, and a test reuses one instance for every request it
 * makes. Forgetting the guards makes the next request authenticate from its
 * own Authorization header again, as a separate HTTP request would.
 */
function asNewRequest(): void
{
    resolve('auth')->forgetGuards();
}

/**
 * Assert that a response is the API envelope and nothing else.
 */
function assertEnvelope(TestResponse $response, bool $success): void
{
    $json = $response->json();

    expect($json)->toBeArray()
        ->and(array_diff(array_keys($json), ['meta', 'debug']))->toBe(['success', 'message', 'data', 'errors'])
        ->and($json['success'])->toBe($success)
        ->and($json['message'])->toBeString()->not->toBeEmpty();

    if ($success) {
        expect($json['errors'])->toBeNull();
    } else {
        expect($json['data'])->toBeNull();
    }
}
