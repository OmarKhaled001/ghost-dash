# GhostDash conventions

This application was created from the GhostDash starter kit. README.md describes what exists and DECISIONS.md explains why. Read both before changing structure.

## Where code goes

- Business logic lives in a `final readonly` class in `app/Services`, named `*Service`. Create one with `php artisan make:service Name`.
- Data access goes through an interface in `app/Repositories/Contracts` and an `Eloquent*` implementation in `app/Repositories/Eloquent`, bound in `RepositoryServiceProvider`. Create all three with `php artisan make:repository Model`.
- Filament resources, Filament pages and API controllers stay thin. They validate, call a service for every write, and present the result. They do not use the `DB` facade.
- API controllers must not import models, repositories or query builders. An architecture test enforces this.

## PHP

- Every PHP file starts with `declare(strict_types=1);`. Every parameter, property and return value is typed.
- Classes are `final` unless they are designed for extension. If one must stay open, add it to the ignore list in `tests/Arch/ArchTest.php` with the reason.
- Closed sets of values are backed enums in `app/Enums`, not string constants.
- Comments explain why something is the way it is. Do not write comments that restate the code.

## Two kinds of people

- `App\Models\Admin` signs in to the Filament panel on the `admin` guard. `App\Models\User` is an end user on the `web` and `sanctum` guards. They never share a guard, provider, password broker or table.
- Roles and permissions exist only on the `admin` guard. Use `Role::GUARD` rather than relying on spatie's default guard.

## Authorization

- Every Filament resource needs a policy; the panel uses `strictAuthorization()` and throws without one.
- After adding a resource, page or widget, run `php artisan shield:generate --all --panel=admin --option=policies` and commit the policy, then `php artisan dash:sync-permissions`.
- A page that is not a resource implements `App\Filament\Contracts\GatedByPermission` and uses `App\Filament\Concerns\AuthorizesWithPermission`, or uses Shield's `HasPageShield`. The authorization tests discover pages automatically and fail for one that has no gate.
- Guard rails (no self-delete, last super admin, super-admin-only actions, nobody grants or manages more access than they hold) are enforced in `AdminService` and `RoleService` and repeated in the policies. Do not bypass the services to write admins or roles.
- When a feature lets one admin act on another, ask `Admin::hasAuthorityOver()`; when it hands out a role, ask `Admin::mayGrant()`.
- Anything that lists admins or their activity starts from `AdminRepository::queryVisibleTo()` or `Activity::visibleTo()`, so super admins stay invisible to other admins.

## Database

- MySQL 8.4 only. Do not add SQLite fallbacks.
- Foreign keys state their `onDelete` behaviour explicitly. Columns used in `WHERE` or `ORDER BY` get an index.
- Multi-step writes are wrapped in `DB::transaction()` inside the service.
- Models are strict outside production: lazy loading and reading attributes that were not loaded both throw. Eager load in Filament tables with `modifyQueryUsing()`.

## Uploads

- Image uploads take `->acceptedFileTypes(RasterImage::MIME_TYPES)` and a `->maxSize()`. Filament's `->image()` alone accepts SVG, which can carry script and would be served from this application's origin.
- Keep Filament's random file names. Do not use `preserveFilenames()` on a local or public disk.

## Settings

- A settings group is a spatie/laravel-settings class in `app/Settings`, a settings migration in `database/settings`, and a `SettingsTab` in `app/Filament/Settings/Tabs`, which appears on the Settings page by itself. Create all of them with `php artisan make:settings-tab Name`.
- Add a setting with a property, a new settings migration, and a field on the tab. Never edit a settings migration that has already run.
- Settings are saved through `SettingsService`, which logs each change. Read them with `resolve(SomeSettings::class)`.
- Expose a group to `GET /api/v1/settings` only through `PublicSettings::toPublicArray()`, listing each field on purpose.
- Tab labels and fields are translated in `lang/{en,ar}/settings/<group>.php`.

## Text

- Every string a person reads in the panel, an API response or an email goes through `__()` and has an entry in both `lang/en` and `lang/ar`. A test compares the two directories key by key.
- Command-line output is English only.

## Activity log

- Models that should be audited use `Spatie\Activitylog\Models\Concerns\LogsActivity` with `logOnly([...])->logOnlyDirty()->dontLogEmptyChanges()`.
- Never add your own masking for secrets: `App\Support\SensitiveData` masks sensitive keys for every activity and for everything Telescope records. Extend the list in `config/ghostdash.php` instead.
- Add `ActivitiesRelationManager::class` to a resource's `getRelations()` to give its records a timeline.

## Tests

- Pest, against a real MySQL testing database. `composer check` must pass before a change is done: Pint, Rector, Larastan and the test suite.
- Use the helpers in `tests/Pest.php`: `admin()`, `adminWith([...permissions])`, `superAdmin()`, `actingAsAdmin()`.
- Do not create a PHPStan baseline and do not add `@phpstan-ignore` comments. Fix the type instead.
