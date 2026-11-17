<laravel-boost-guidelines>
=== .ai/ghostdash rules ===

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
- A credential in a settings group goes in the settings class's `encrypted()` and the tab's `secrets()`, so it is stored encrypted and never sent to the browser.

## SEO

- SEO is switched on by `GHOSTDASH_SEO` (`php artisan dash:seo`). Anything new for SEO checks `SeoService::enabled()`.
- Resolve a page's SEO with `SeoService::resolve($path)`, never by reading `SeoPage` and `SeoSettings` yourself: it normalises the path and applies the fallbacks.

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

=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
