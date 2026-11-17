# Decisions

Choices made while building GhostDash that are not obvious from the code, newest phase last. Each entry says what was decided and why, so a later reader can tell whether the reason still holds.

## Version matrix

Resolved from Packagist on 2026-11-15 (`composer show <pkg> --all` / the p2 metadata API), then confirmed by letting Composer resolve the whole set on PHP 8.4.27 and again on PHP 8.5.12. Both resolutions produce the same lock file.

| Component | Version | Constraint that matters |
|---|---|---|
| PHP | 8.4 minimum, 8.5 supported | `spatie/laravel-activitylog` 5, `spatie/laravel-backup` 10 and Pest 5 all require `^8.4` |
| MySQL | 8.4 LTS | |
| Laravel | 13.42 | |
| Filament | 5.11 | |
| Livewire | 4.4 | pulled in by Filament 5 |
| Tailwind CSS | 4.x | `@tailwindcss/vite` |
| Vite | 8.x | `laravel-vite-plugin` 3 |
| Laravel Sanctum | 4.4 | |
| Laravel Telescope | 5.28 | |
| Laravel Prompts | 0.3 | |
| bezhansalleh/filament-shield | 4.3 | requires `filament/filament ^4.0\|^5.0`, `spatie/laravel-permission ^6\|^7\|^8` |
| spatie/laravel-permission | 8.5 | |
| spatie/laravel-activitylog | 5.1 | |
| bezhansalleh/filament-language-switch | 5.1 | requires `filament/filament ^4.0\|^5.0` |
| spatie/laravel-settings | 3.10 | |
| filament/spatie-laravel-settings-plugin | 5.11 | versioned with Filament |
| spatie/laravel-backup | 10.4 | |
| spatie/laravel-health | 1.41 | |
| Pest | 5.3 (PHPUnit 13) | see below |
| Larastan | 3.15 (PHPStan 2.3) | |
| Pint | 1.32 | |
| Rector | 2.8 with driftingly/rector-laravel 2.7 | |
| Laravel Boost | 2.10 | |

Compatibility was read from each package's `composer.json` constraints, not its README.

## Phase 1: scaffold and tooling

**Pest 5 instead of the expected Pest 4.** The brief named Pest 4 as the expected baseline and allowed a newer major when every package supports it. Pest 5.3 is current, and `pest-plugin-laravel` 5.0, `pest-plugin-livewire` 5.0 and `pest-plugin-arch` 5.0 all resolve against Laravel 13 and Livewire 4, so the kit uses Pest 5.

**README.md was written from the brief.** The brief describes README.md as an existing contract, but the working directory was empty when the build started. The README in this repository was therefore written from the brief and is the contract from here on. Every name in it (options, scripts, endpoints, schedule) was chosen once and then implemented to match.

**The test suite refuses to run against a database whose name lacks `_testing`.** Laravel falls back to `.env` when `.env.testing` is missing, and `RefreshDatabase` would then wipe the development database. `Tests\TestCase` checks the name before every feature test. `DB_*` is deliberately not set in `phpunit.xml`, because a value there would silently win over `.env.testing`.

**`final` is enforced by an architecture test, not by Pint's `final_class` fixer.** The fixer would silently finalise a class someone left open on purpose. The arch test fails instead and lists the allowed exceptions in one place.

**`composer dev` runs `php artisan dev`.** Laravel 13 ships this command; it runs the server, a queue listener, Pail and Vite. The kit keeps the skeleton's script instead of maintaining its own `concurrently` line.

**`laravel/pao` is kept.** It is part of the current Laravel skeleton's dev dependencies and only changes test and linter output when an AI agent runs them.

**Telescope is excluded from package discovery.** The brief requires that Telescope does not load at all when `TELESCOPE_ENABLED` is false. Discovery would register its provider unconditionally, so it is listed under `dont-discover` and registered by hand.

**Security headers.** `X-Frame-Options: SAMEORIGIN` rather than `DENY`, so the panel can still frame its own pages (file previews, embedded reports). HSTS is sent only in production and only on HTTPS requests. No Content-Security-Policy is sent: Filament and Livewire rely on inline scripts, and a CSP loose enough to allow them would protect little. Add one per project together with nonces if you need it.

**The root URL redirects to the panel.** The kit has no public site, so `/` redirects to `ADMIN_PANEL_PATH`.

## Phase 2: admin guard and panel

**Admins have their own password reset table.** `admin_password_reset_tokens` keeps a reset requested for a user from ever being redeemed against an admin who shares the email address, and the other way round.

**`EnsureAdminIsActive` is registered in Laravel's middleware priority list.** Laravel hoists every middleware implementing `AuthenticatesRequests` ahead of ordinary middleware, whatever order a route lists them in. Without the priority entry Filament's `Authenticate` ran first and answered a deactivated admin with 403. The middleware only signs the admin out; `Authenticate` then redirects them to the login page like any guest, which behaves the same for page loads and Livewire calls.

**A custom `Login` page exists only to raise `Lockout`.** Filament throttles the form with its own rate limiter (kept, as the brief asks) and never dispatches Laravel's `Lockout` event, so admin lockouts would be missing from the activity log.

**`Admin` overrides the verification and reset notifications.** Laravel's defaults link to `verification.verify` and `password.reset` routes that do not exist here. The overrides send Filament's notifications with panel URLs, so a reset started outside the panel (a console command, a future "send reset link" action) still produces a working link.

**The panel uses a Vite-built theme.** In-house pages use Tailwind classes that Filament's precompiled CSS does not contain, so the panel loads `resources/css/filament/admin/theme.css` through Vite. This is why the installer builds assets. Tests call `withoutVite()` and do not need a build.

**IBM Plex Sans Arabic is the panel font.** It carries matching Latin and Arabic glyphs, so both languages look like one typeface. It is loaded through Filament's default font provider (Bunny Fonts).

**`strictAuthorization()` is on.** Filament throws when a resource has no policy, so a forgotten policy fails loudly in development instead of leaving a resource open.

## Phase 3: roles, permissions and guard rails

**Permission names are `snake_case` with a `:` separator: `view_any:admin`, `update:role`.** The brief's custom permissions are snake_case (`view_telescope`), and Shield 4 defaults to `ViewAny:Admin`. Shield refuses `_` as the separator together with snake case, because the boundary between action and subject would be ambiguous, so `view_any_admin` is not available. Snake case with `:` keeps one style across generated and custom permissions. Custom permission keys are not reformatted (`format_custom_permission_keys` is off).

**`super_admin` is a real role holding every permission, not a `Gate::before` bypass.** With the bypass, policies never run for a super admin, and the guard rails in them (no self-delete, for instance) would not apply to exactly the people most able to break things. `dash:sync-permissions` grants new permissions to the role on every deployment.

**Shield's `panel_user` role is disabled.** `canAccessPanel()` requires an active admin with at least one role, as the brief specifies, so a catch-all role handed to everyone would defeat that check.

**Guard rails live in services, and policies repeat them.** `AdminService` and `RoleService` throw `GuardRailViolation`, which is the layer a new page, a console command or a job cannot bypass. Policies apply the actor-versus-target rules so the panel hides what would fail. The "last active super admin" rule is checked only in the service, inside the transaction and with `SELECT ... FOR UPDATE`, because a policy would have to count rows for every table row it renders and could still race.

**"Last super admin" means last *active* super admin.** An inactive super admin cannot sign in, so they do not count as someone who could still manage roles. Deactivation is treated like deletion and demotion.

**The `super_admin` role itself cannot be edited or deleted, by anyone.** It always holds everything and the kit looks it up by name. Non-super admins cannot see the role or its members; the base queries of `AdminResource` and `RoleResource` exclude them, so table rows, typed URLs (404) and filter options are all covered at once.

**A role editor cannot hand out permissions they do not hold.** Without this rule `update:role` would equal super admin: grant yourself everything, done. Permissions beyond the editor are frozen on save: they stay if the role had them and cannot be added if it did not. This was not in the brief; it closes an escalation path the brief's rules leave open.

**A role cannot be deleted while admins hold it.** Deleting it would silently strip access, and lock out anyone whose only role it was.

**Admins and roles have no bulk delete, so no `delete_any` permission.** Each deletion goes through the service individually. Shield's `policies.merge` is off so that the per-resource method lists in `config/filament-shield.php` are exact.

**The Role resource is published from Shield and trimmed.** Shield's own pages write permissions directly (`syncPermissions` in `afterSave`). The published copy keeps Shield's permission tabs and sends writes through `RoleService`. Tenancy and the guard-name field were removed because the kit has one guard.

**Admins created by another admin are marked verified.** Whoever creates the account vouches for the address, and a fresh install usually has no working mailer. Changing an email address later still requires verification.

**Repository contracts are named `AdminRepository`, implementations `EloquentAdminRepository`.** Laravel's own contracts carry no `Interface` suffix, and the prefix keeps the two apart when both are imported.

**An inline SVG avatar provider replaces Filament's default.** The default asks ui-avatars.com for each avatar, which sends every admin's name to a third party from every browser that opens the panel.

**`UserResource` exists although the brief does not list it.** The brief asks for the repository pattern on `User`, activity logging on `User` and a limited admin "who sees only what they are permitted to see". A user resource is what makes those testable in the panel.

## Phase 4: activity log

**Redaction happens in one place, by key fragment.** `RedactedLogActivityAction` masks any attribute or property whose key contains `password`, `secret`, `token`, `recovery_code`, `authorization`, `cookie` or `api_key`, at any nesting depth, for every model and every manual `activity()` call. A model that forgets to exclude a column therefore still cannot leak it. `null` stays `null`, so removing a secret is distinguishable from changing it.

**`password` is logged on purpose.** It is in each model's `logOnly()` list so that a password change appears in the audit trail. The stored value is always `[redacted]`.

**Pivot changes are logged in the models, not the services.** `Admin::syncRoles()` and `Role::syncPermissions()` / `givePermissionTo()` are wrapped to record what was attached and detached. Logging in the services would have missed every other caller: the installer, `shield:super-admin`, a tinker session. Spatie's own attach/detach events were not used because a sync fires "detach everything, attach everything", which is noise rather than a diff. A sync that changes nothing logs nothing.

**The causer is resolved by asking the guards in order: `admin`, `sanctum`, `web`.** The package only asks the default guard (`web`), which is never an admin. The override is registered with `resolving()` so it survives Octane rebuilding the scoped resolver per request. An explicit `causedBy()` still wins.

**Multi-factor changes are detected on the model.** Filament enables and disables MFA by saving columns and raises no event, so `Admin` compares the old and new column values in its `updated` hook and logs `mfa_enabled` / `mfa_disabled`. Replacing one authenticator secret with another is not logged as enabling again.

**Lockouts carry their guard and email through request attributes.** `Lockout` holds only the request, and for the panel that request is a Livewire call whose payload does not expose the form fields. The login page sets two request attributes before dispatching the event.

**A morph map is registered, without enforcing it.** `admin`, `user`, `role` and `permission` are stored instead of class names, so renaming or moving a model later does not orphan activity, role assignments or API tokens. It is not enforced because third-party packages morph to models the kit does not own.

**The activity log is read-only for everyone.** `ActivityPolicy` denies create, update and delete even to super admins, and the resource has only an index page. Old entries leave through `activitylog:clean`, never through the panel.

**The "Activity" timeline is a relation manager.** It reuses the main table definition, shows only that record's entries, and is visible only with `view_activity_log`: being allowed to see a record does not include seeing who changed it.

**`ActivityResource` is excluded from Shield and gated by `view_activity_log`.** It implements `GatedByPermission`, which is also how the authorization tests know what to grant.

**CLI output is English only.** The brief asks for every user-facing string to go through `__()`. That is applied to the panel, API responses, validation messages and emails. The installer and `dash:doctor` speak English: Arabic does not render reliably in terminals (no shaping, reversed order on Windows consoles), and their audience is the developer installing the kit.

## Phase 5: Telescope

**Telescope is registered by hand from `AppServiceProvider::register()`.** It is excluded from package discovery and both its own provider and the application's `TelescopeServiceProvider` are registered only when `config('telescope.enabled')` is true. Switched off, there are no Telescope providers, routes, watchers or commands. `config/telescope.php` defaults `TELESCOPE_ENABLED` to `false`, so a missing variable fails closed; `.env.example` sets it to `true` for local development.

**The Telescope migration is always present.** Telescope has to be switchable in production without a deployment, so its tables are created by the normal `migrate` run whether or not it is enabled.

**Authorization asks the `admin` guard directly.** Telescope's stock gate receives `$request->user()`, which is the default (`web`) guard: an end user or nobody. `TelescopeServiceProvider::authorization()` replaces the callback and checks that the admin-guard user is an active `Admin` with `view_telescope`. A test signs an end user in on the `web` guard and confirms they are turned away.

**Guests are redirected instead of receiving 403.** Telescope answers 403 to anyone it does not recognise. `RedirectTelescopeGuestsToLogin` sends browsers that are not signed in to the panel login and back afterwards; JSON calls from Telescope's own frontend still get the 403.

**Sensitive request data is hidden in every environment, local included.** A password typed into a local form is still a password, and local databases get copied around.

**Scheduled-task entries are not recorded outside local.** Telescope's stub keeps them; the brief lists exceptions, failed requests, failed jobs, slow queries and tagged entries only.

**`telescope:prune` is scheduled only while Telescope is enabled.** The command does not exist otherwise and the scheduler would fail on it every day.

## Phase 6: extras

**The language choice is stored on the admin record as well as in the session and a cookie.** The switcher plugin keeps it in the session and a cookie, which belong to one browser. `admins.locale` makes the panel open in the admin's language wherever they sign in.

**The configured default language beats the browser's `Accept-Language`.** The plugin consults the browser language before its own cookie and the app default. Many people who want an Arabic panel run an English operating system, so an admin-chosen default must not lose to it. The plugin's `userPreferredLocale` callback always answers (saved choice, then cookie, then the default from General settings), which takes the browser header out of the decision.

**Settings are applied by middleware, never in a service provider.** They live in the database, and providers also boot for commands that run before a database exists (`key:generate`, the installer). `ApplyGeneralSettings` sets the app name, default locale and display time zone per request; panel branding uses closures for the same reason.

**The time zone setting changes display only.** Timestamps stay in UTC in the database; `FilamentTimezone` converts them for the panel.

**Logo and favicon uploads accept PNG, JPEG and WebP, not SVG.** An SVG can carry script and uploads are served from the application's own origin.

**Settings, backup and activity pages are gated by custom permissions through `GatedByPermission`.** They are excluded from Shield's generated page permissions. The authorization tests discover every registered page and use that interface (or Shield's page permission) to know what to grant, and fail for a page with no gate at all.

**The Backups and Health pages are built in-house.** The brief allows a plugin or an in-house page. In-house keeps authorization, translations and the path checks under the kit's control and removes two third-party packages from the upgrade path.

**Backup paths are never trusted.** Download and delete receive a path from the browser. `BackupService` resolves it against the list of archives spatie/laravel-backup reports and answers 404 for anything else, so a crafted value cannot reach other files on the disk. Requesting, downloading and deleting a backup are written to the activity log.

**Creating a backup from the panel is queued.** A dump inside a web request would hold the request open for as long as the dump takes. A queue worker must be running; `composer dev` starts one.

**Backups are written to a dedicated `backups` disk under a fixed folder name.** The disk lives outside `storage/app/public`, so a backup never contains earlier backups. The folder is `app` rather than `APP_NAME`: renaming the application would otherwise start a second, unmonitored folder.

**Backup mail is sent only when `BACKUP_NOTIFICATION_EMAIL` is set, and only for events worth reading.** With no address, no notification has a channel. "Cleanup succeeded" and "healthy backup found" are not sent at all: a daily mail saying nothing is wrong trains people to ignore the sender. spatie/laravel-backup validates `mail.to` even when unused, so the config falls back to `MAIL_FROM_ADDRESS` purely to satisfy that check.

**The dump is not gzipped.** The archive is a zip already, and a gzip step needs a binary Windows servers do not have.

**Disk space is measured by an in-house check.** spatie/laravel-health's `UsedDiskSpaceCheck` shells out to `df`, which does not exist on Windows. `DiskSpaceCheck` uses PHP's own functions.

**Debug mode, environment and optimised-app checks run only in production.** On a development machine they would be red forever, and a page that is always red stops being read.

**Health check labels are translated when shown, not when stored.** Results are written by the scheduler in whatever language it ran in. The page translates from the stable check name. Failure messages come from the checks themselves and stay in English.

**Health notifications are off by default.** Results are read on the Health page. `HEALTH_NOTIFICATIONS_ENABLED` and `HEALTH_TO_ADDRESS` turn mail on.

**Two Rector rules are skipped.** `ApplyDefaultInsteadOfNullCoalesceRector` rewrote `config('key') ?? 'x'` into `config('key', 'x')`, which is not equivalent when the key exists with a null value and turned a table name into an empty string. `SpatieEnumMethodCallToEnumConstRector` rewrote `Status::ok()` into a constant that spatie/laravel-health's enum does not have. Both are documented in `rector.php`.

**Boost output is committed for one agent, with the kit's conventions merged in.** `php artisan boost:install` generated `AGENTS.md`, `.mcp.json` and `.claude/skills` for the agent it detected. The kit's own rules live in `.ai/guidelines/ghostdash.md`, which Boost merges into `AGENTS.md`. Run `php artisan boost:install` again to target a different agent. The Laravel Cloud deployment skill was left out.

**All Laravel stubs are republished with `declare(strict_types=1)` and `final`.** Otherwise the first `make:model` in a new project would fail the kit's own architecture tests. Generated placeholders (unused imports and the like) are still tidied by `composer lint`, as with stock Laravel.

**`make:repository` edits `RepositoryServiceProvider` in place.** It appends to the `$bindings` array and adds the imports in sorted order, writing through a temporary file so another process never autoloads a half-written provider. An explicit list was preferred over scanning the filesystem at boot: it is visible, greppable and costs nothing per request.

## Phase 7: user API

**The envelope always has the same four keys: `success`, `message`, `data`, `errors`.** Unused keys are `null` rather than absent, so a typed client needs one response model, not one per outcome. `meta` is added only for paginated collections and `debug` only when `APP_DEBUG` is on.

**Wrong credentials are a 422 validation error on `email`, not a 401.** 401 means "this request has no valid token". A login attempt that fails is invalid input, and clients already know how to show a field error.

**Unknown address and wrong password are indistinguishable.** The body is identical, and a password hash is verified even when there is no such user so that the response time is too. The same applies to `forgot-password` (always the same success message) and `reset-password` (one message for a bad token and an unknown address). Without this each of those endpoints is an account-existence oracle.

**Everything under `/api` is rendered by `ApiExceptionRenderer`, whatever `Accept` header was sent.** A browser or a careless client still gets the envelope instead of an HTML error page or a redirect to a login route that does not exist. `redirectGuestsTo()` is set for the same reason: Laravel's default looks up a `login` route and would turn a missing token into a 500.

**A 500 never says what went wrong unless `APP_DEBUG` is on.** The cause goes to the log.

**Each endpoint has its own named rate limiter.** Guest endpoints are keyed by IP plus the email where there is one; authenticated endpoints by user. Separate limiters mean exhausting one (hammering login) does not lock a person out of another (resetting their password). The login limiter also dispatches `Lockout`, so API lockouts appear in the activity log like panel ones.

**Email verification is a signed GET without a token.** The link is opened from a mail client, which has no token to send. The signature proves the link is ours and unexpired; the hash ties it to the address it was mailed to, so an old link stops working after the address changes.

**Password reset links open the frontend; verification links open the API.** The brief asks for reset links to point at `FRONTEND_URL`, where a form has to collect the new password. Verification needs no form, so the link works even for a project that has no frontend yet.

**Verification and reset emails are subclasses, not `createUrlUsing()` callbacks.** Those hooks are static and would change the links for every notifiable in the application, admins included.

**Changing the password requires the current one and signs out other devices; resetting it signs out all of them.** A stolen token alone must not be enough to take over an account, and whoever knew the old password may be holding tokens.

**Logging out needs no ability.** A token must always be able to give up its own access, however narrow it is.

**Registration succeeds even if the verification email cannot be sent.** The failure is reported; the user can ask for the email again. Failing the request would leave an account that exists but cannot be registered again ("email taken").

**Controllers may not import models.** An architecture test forbids `App\Models`, repositories, query builders and the `DB` facade in controllers. `AuthenticatedRequest::authenticatedUser()` gives them a typed user instead.

**`asNewRequest()` exists for tests.** Laravel keeps the authenticated user on the guard for the lifetime of the application instance, and a test reuses one instance for all its requests. Without forgetting the guards, a revoked or expired token would still appear to work on the next request of the same test.

## Phase 8: installer

**The installer keeps no state file.** "Resumable" is implemented by every step inspecting what already exists (an `.env` value, a database, a migration, a super admin) and doing only what is missing. A progress file could disagree with reality after a manual fix; reality cannot.

**The database is probed with PDO, not through Laravel.** The installer has to know whether credentials work before the application depends on them, and the running process booted with whatever `.env` held before (the placeholders, on a fresh install). After a successful probe the runtime configuration is repointed and the connection purged, so migrations in the same process use the new database.

**Database names are restricted to letters, digits and underscores.** The name goes into `CREATE DATABASE`, where it cannot be a bound parameter.

**Placeholders from `.env.example` are not treated as answers.** `APP_NAME=GhostDash`, `APP_URL=http://localhost:8000` and `DB_DATABASE=my_app` are what the file ships with, not something anyone chose, so the installer proposes the directory name and a detected Herd / Valet URL instead. Any other existing value is offered as the default.

**`.env` values are quoted only when a dotenv parser would misread them.** `EnvFile` round-trips spaces, `#`, `$`, quotes and backslashes (tested against the real parser). `=` stays unquoted because `php artisan key:generate` only finds an unquoted `APP_KEY`.

**The installer always writes `.env`,** even when the command itself runs under another environment file such as `.env.testing`.

**In production with `--no-interaction`, `.env` is read-only to the installer** and the testing database, asset build and git steps are skipped. The brief says "unless asked", so `--testing-db`, `--assets` and `--git` exist to ask.

**`--demo` outside local is refused before anything is written.** An option that will be rejected should not leave a half-installed application behind first.

**Settings follow the installer's answers only on a first install, or when `--app-name` / `--locale` are given.** Later runs must not undo what an admin changed on the settings page.

**The installer's own exit code ignores `dash:doctor`.** The doctor runs at the end as advice: right after an install the scheduler cannot be running yet.

**`dash:doctor` reports Telescope outside local as a warning, not a failure.** It is gated by `view_telescope` there, which is what the brief asks the check to verify ("disabled or gated"); the warning is a reminder that it is still recording.

**`dash:doctor` judges `APP_ENV` by `APP_URL`.** It cannot know whether a machine is a server. A public-looking URL with a non-production environment is the closest thing to evidence that someone forgot to switch it.

**Heartbeat checks reuse the health checks.** They already know where heartbeats are stored and how old one may be.

**`composer create-project` ends in a hidden `dash:post-create` command.** It starts the installer when a person is at a terminal and prints the next step otherwise (`GHOSTDASH_SKIP_AUTO_INSTALL=1`, `composer --no-interaction`, no TTY). It passes `--no-serve`: a development server must not live inside a Composer script. `Composer\Config::disableProcessTimeout` is first in the hook because Composer kills scripts after 300 seconds, which an installer waiting for answers would exceed. No migration runs before the installer has the credentials.

**`laravel new --using` needs the second step, by design of the Laravel installer.** Verified with laravel/installer 5.33: it creates the project with `LARAVEL_INSTALLER_DEFER_HOOKS=1` (the kit waits), then runs the hooks listed under `extra.laravel.installer` as a captured task with no terminal, so the kit prints "Next: cd <dir> && php artisan dash:install". `--database=mysql` is required: without it the Laravel installer assumes SQLite for starter kits and rewrites `.env` and `.env.example`. With it, the Laravel installer runs one `php artisan migrate` with the placeholder credentials; that cannot be prevented from the kit and is documented in the README.

**Path-repository copies are controlled by `.gitattributes`, not `.gitignore`.** Composer 2 mirrors a path repository (`GHOSTDASH_SOURCE`) by `export-ignore` rules only, so a project created from a working copy would otherwise inherit that copy's `.env`, `vendor/` and `node_modules/`. The same file keeps kit-only files (`install.sh`, `install.ps1`, the e2e workflow, the changelog) out of created projects.

**Both scripts set `COMPOSER_MIRROR_PATH_REPOS=1` and refuse a linked result.** Composer may symlink a path repository instead of copying it. The "new project" would then be the source checkout itself, and everything after it (key generation, the installer, `git init`) would modify that. This happened once during development; the scripts now force a copy and stop if the target turns out to be a link.

**`install.ps1` describes the local checkout in a temporary `packages.json` instead of passing JSON on the command line.** Windows PowerShell 5.1 strips double quotes from arguments passed to `composer.bat`, so `--repository='{"type":"path",...}'` arrives as broken JSON. For the same reason no double quotes are embedded in the PHP and Node one-liners it runs.

**`install.ps1` does not call `exit` under `irm | iex`.** There is no script to exit from, and `exit` would close the person's terminal. It sets `$LASTEXITCODE` and returns; it exits only when run from a file or in CI.

**`install.sh` works on bash 3.2.** macOS still ships it, and `curl | bash` uses whatever `bash` is first on `PATH`. No associative arrays, no `${var,,}`, and empty arrays are expanded with the `${array[@]+...}` guard.

**The e2e workflow signs in over HTTP.** `.github/scripts/assert-admin-sign-in.php` loads the login page, submits the Livewire form with the page's own snapshot and CSRF token, and opens the panel with the resulting session. It reads the password from the environment and never prints the response, which echoes the form state.

**The e2e workflow uses `shogo82148/actions-setup-mysql` rather than a service container.** Service containers do not exist on Windows runners; this installs the same MySQL 8.4 on both.

**The installer tests live in `tests/Installer`, outside the database trait.** They create and drop their own databases and repoint the connection. `LazilyRefreshDatabase` would wrap that in a transaction on the wrong database and record the wrong database as migrated.

## Phase 9: review, verification and documentation

**Image uploads accept PNG, JPEG and WebP only.** Filament's `image()` means `image/*`, which includes SVG. An uploaded file is served from the application's own origin, and an SVG opened directly can run script there. Avatars, the logo and the favicon share one list in `App\Filament\Support\RasterImage`.

**`TRUSTED_PROXIES` was added.** It is not in the brief. Without it, a deployment behind a load balancer keys every API rate limit on the balancer's address, records that address as the admin's last sign-in IP, and cannot tell that the original request was HTTPS. Laravel's `TrustProxies` middleware already reads `config('trustedproxy.proxies')`, so the kit only ships that config file. Empty means nothing is trusted.

**The Telescope tests repeat part of Laravel's `setUp` after rebuilding the application.** They rebuild it to exercise the real `TELESCOPE_ENABLED` switch. A rebuilt application is back on the database from `.env.testing` with no transaction, which under `pest --parallel` is neither this process's database nor rolled back. The suite passed on the development machine only because that database had been migrated by earlier serial runs; it failed in a freshly created project. Found by running `composer check` inside a project created by `install.sh`, which is now part of the release checks below.

**Installer scratch databases are named `<testing database>_it_<random>`.** The earlier fixed prefix needed a second MySQL grant that the README did not mention.

**`composer audit` reports abandoned packages without failing** (`config.audit.abandoned` in `composer.json`). `symplify/rule-doc-generator-contracts` arrives through rector-laravel, is abandoned and has no replacement, and the default would keep CI red over something no project can act on. Known vulnerabilities still fail the build.

**Screenshots are not committed.** The browser run that checked the definition of done produced them, but they show one particular demo database and would go stale with the next Filament release.

**The first release is `v1.0.0`, and the owner creates the tag.** `composer create-project` asks for a stable version unless told otherwise, and the README promises "the latest stable tag" as the default, so a tag is part of making the documented commands work. Everything in the brief is implemented and both workflows are green. A starter kit is copied, not depended on, so the number carries no upgrade promise beyond the changelog. The build environment may push branches but not tags, which is why the tag is not there yet.

### Security review

An independent, read-only review of the whole kit was run against the rule in the brief (authorization everywhere, IDOR-safe queries, validation, rate limits, nothing sensitive logged). Everything it confirmed was fixed, each fix with a test that fails without it.

**Editing admins is not a way to gain access.** A non-super admin holding `update:admin` could assign themselves every role except `super_admin`, or set the password of an admin with more access and sign in as them. `Admin::mayGrant()` and `Admin::hasAuthorityOver()` now state one rule, that you can only hand out or take charge of permissions you hold, and `AdminService`, `AdminPolicy` and the role picker all use it. The same idea already governed role editing (`RoleService`).

**Super admins are left out of the activity log for other admins.** The brief says non-super admins cannot see super admins; the log named them with email and IP address on every sign-in. The cost is that an auditor who is not a super admin does not see what super admins did. Auditing super admins is a super admin's job.

**Telescope redaction is by key name, at any depth.** Telescope's hide lists match exact paths from the top level. A Livewire form posts `components.0.updates."data.password"` and repeats it inside a JSON snapshot; the API returns `data.token`. Neither matched. `RedactingRequestWatcher` is bound in place of Telescope's request watcher and applies `SensitiveData`, the same list and logic the activity log uses.

**Login and password reset limits have a second, per-IP limit**, and the per-address key is folded to ASCII. The email column's collation ignores case and accents, so `víctim@` and `victim@` are one account that used to get separate counters.

**A lockout is logged once per minute per key.** Each refused request used to add a row, which let an anonymous client grow the table at will.

**Account existence is not readable from timing.** The decoy hash for unknown addresses was a `static` variable, which lasts one request under PHP-FPM, so an unknown address cost two hash operations and a known one cost one. It is cached now. The reset mail is sent with `defer()` after the response. Under a server that cannot finish the response early (Apache with mod_php), a careful observer could still time the end of the connection; PHP-FPM and Octane are not affected.

**Registration still says when an address is taken.** Hiding that would mean answering "check your mail" to every registration and issuing no token until the address is verified, which is a different API from the one in the README. It is limited to 5 requests a minute per IP.

**`logout-all` needs `account:write`.** A token narrowed to reading the account could revoke all the others.

**An admin setting a user's password revokes that user's tokens**, as the user's own reset does.

**Upload fields refuse a stored path other than their own.** Filament validates uploaded files but passes a plain string through, so the browser could point the column at any path on the disk.

**Requests for unknown host names are refused outside local.** Laravel builds absolute URLs from the `Host` header, and Filament builds the admin password reset link that way. With a catch-all virtual host, or with `X-Forwarded-Host` once a proxy is trusted, a forged host would be mailed to an admin inside a valid reset link. `trustHosts()` limits requests to the host in `APP_URL`, its subdomains and `TRUSTED_HOSTS`. The price is that a wrong `APP_URL` on a server now answers 400 to everyone, so `dash:doctor` has a check that says so.

**`TRUSTED_PROXIES=*` is documented as a last resort.** Laravel turns `*` into "trust every address in the chain", which makes the client-written start of an appended `X-Forwarded-For` the client address. Explicit proxy addresses or ranges do not have that problem; a test pins both behaviours.

**The bootstrap scripts mask `--*password*=` options** in the command line they print.

Considered and left alone:

- **`.env` is not made owner-readable only.** Many servers deploy as one user and run PHP as another; `chmod 600` would take the application down there. File permissions belong to the deployment.
- **`manage_backups` stays an ordinary permission.** An archive holds the whole database, which the README now says next to the permission, together with `BACKUP_ARCHIVE_PASSWORD`.
- **Failed sign-ins are still logged one row each.** They are bounded by the rate limits, and they are the audit trail.

### How the definition of done was checked

| Item | How | Result |
|---|---|---|
| `composer check` locally | In the kit, in a clean clone without `.env` following `ci.yml` step by step, and inside a project created by `install.sh` | Pass |
| One command to a running panel | `install.sh` piped into `bash` and `install.ps1` through `Invoke-Expression` (PowerShell 7 on Linux), both with `GHOSTDASH_SOURCE`; also from a shallow, detached checkout as GitHub Actions makes one | Pass |
| The steps of `install-e2e.yml` | Run by hand on Linux: project complete, kit-only files absent, `dash:doctor` exit 0, second installer run changes nothing, panel served, super admin signs in over HTTP | Pass |
| `composer create-project` | In a pseudo-terminal with a path repository: the installer starts by itself | Pass |
| `laravel new --using` | laravel/installer 5.33 with a path repository | Pass, with the documented second step |
| Sign in, switch to Arabic | Chromium through Playwright: `dir="rtl"`, `lang="ar"`, Arabic navigation, and the choice survives signing out and in | Pass |
| Limited admin | Created through the form with the `support` role: sees Dashboard and Users only, every other page answers 403 | Pass |
| Activity log | The limited admin's rename of a user appears with causer and a before/after diff | Pass |
| Telescope | Outside `local`: guest redirected to the login and back after signing in, admin without `view_telescope` 403, super admin 200 | Pass |
| Backup | `backup:run` in a created project produced a zip containing `db-dumps/mysql-<database>.sql` | Pass |
| Composer straight from GitHub | `composer create-project omarkhaled001/ghost-dash app dev-main` with the public repository given as a VCS source, then `dash:install` and `dash:doctor` | Pass; the downloaded archive leaves out the kit-only files |
| Both workflows on GitHub | First push to `main` of `omarkhaled001/ghost-dash`: `ci.yml` on PHP 8.4 and 8.5, `install-e2e.yml` lint job, Ubuntu with bash, Windows with PowerShell 7 and Windows with Windows PowerShell 5.1 | Pass, all six jobs, first run |

### Not verified

- **`composer create-project omarkhaled001/ghost-dash` and the one-line commands, exactly as written.** They resolve the package through Packagist and want a stable tag; the tag and the Packagist submission are the owner's to do (see the TODOs). Everything around that lookup is verified: the repository is public, the bootstrap scripts are served from their `raw.githubusercontent.com` addresses, and the workflows run the rest through `GHOSTDASH_SOURCE`.
- **macOS** and bash 3.2. `install.sh` avoids bash 4 features and passes shellcheck, but was only run on bash 5 (locally and on GitHub's Ubuntu runner).

## Phase 10: settings tabs and SEO

Asked for after 1.0.0: an option and a command that create SEO, more default settings, and an easy way to add a settings tab in code.

**One Settings page with a tab per settings class.** The General settings page was a Filament `SettingsPage`, which is one form for one class. A tab per class keeps each class small and lets a project add groups without touching existing ones. The page is a plain Filament page that builds its tabs from `SettingsTabs`, fills each under its group key, and saves through `SettingsService` in one transaction, which also brings settings writes in line with the rule that every write goes through a service.

**Tabs are discovered, not registered.** Every concrete `SettingsTab` in `app/Filament/Settings/Tabs` appears on the page; `config('ghostdash.settings.tabs')` takes tabs from elsewhere. That makes adding a tab a matter of files only, which is also what lets `make:settings-tab` and `dash:seo` generate a tab without editing any existing file.

**`SettingsTab` is an abstract class rather than an interface.** It carries the defaults (icon, position, permission) and the two derived values every tab needs (`key()`, `values()`), so a tab states only what is particular to it. It is on the arch test's list of classes designed for extension.

**A tab may ask for a further permission.** `manage_settings` opens the page; `permission()` hides a tab from admins without its permission, and a hidden tab is not in the form at all, so a value forged for it in the browser is never saved.

**Each tab has its own translation file, `lang/<locale>/settings/<group>.php`.** A generator can create a file but cannot safely edit a PHP array in a shared one. The parity test now walks the language directories recursively. Activity diffs for settings take their field labels from the same file.

**The contact email moved to the Contact tab.** It belongs with the phone and address. A settings migration renames `general.contact_email` to `contact.email`, keeping the stored value.

**Appearance offers named palettes, not a colour picker.** Each Tailwind palette comes with the eleven shades Filament needs and readable contrast; a single picked colour would need both generated. The value is read when the panel boots for a request (`colors()` with a closure) and an unknown value falls back to indigo.

**SEO is generated into the project, not shipped switched off.** "A command that creates SEO" reads as code the project then owns and edits: titles, extra tags, a sitemap later. `dash:seo` writes the settings class, its migration, the tab, an `<x-seo>` class component and both translations from `stubs/seo`, and migrates. It refuses to run in production: source files arrive on a server through a deployment. The component is a class with an inline view so that everything it writes lives under `app/`.

**`<x-seo>` escapes everything and validates what lands in scripts.** Analytics and Tag Manager IDs are checked against their formats on the tab and passed to JavaScript with `@js`; every other value goes through Blade's escaping. Social links must start with `http://` or `https://`, so a `javascript:` link cannot be stored for a website to render.

**`GET /api/v1/settings` exposes only what a settings class lists.** A settings class opts in by implementing `PublicSettings`, and `toPublicArray()` names each field: file paths become URLs, the time zone stays private. The endpoint takes no token because a website needs the values before anyone signs in; it is limited to 120 requests a minute per IP.

**The kit's tests keep passing after a project adds tabs.** They check that the built-in tabs come first and in order and that every file in the directory is found, rather than an exact list. The test that runs the generated SEO code skips itself in a project where `dash:seo` has already run, because the generated classes are then the real ones. Verified by installing a project with `--seo`, adding a tab with `make:settings-tab`, and running that project's `composer check`.

## Phase 11: SEO as a resource; Email and Socialite tabs

Asked for next: SEO as a separate resource, per page and for the site, and settings tabs for Appearance, Email with a test, Socialite and General.

**SEO is a module of its own, shipped with the kit and switched off.** Per-page SEO is a table with a Filament resource, policy, repository and service like any other; site-wide SEO is a settings class with its own page in the same SEO menu. That supersedes Phase 10's generated SEO tab. Generated code could not have been tested by the kit's own suite the way shipped code is, and a resource is far more code to generate. "An option that creates SEO" is kept as a switch: `GHOSTDASH_SEO`, set by `dash:seo` or `dash:install --seo`. Off, the resource and page refuse access and leave the menu, `<x-seo>` renders nothing and the API answers 404. The tables exist either way, so switching on needs no migration. The test suite runs with it on (phpunit.xml), so the resource and page are covered by the authorization tests that discover every page, and separate tests cover it switched off.

**Pages are identified by path.** A path is what both a Blade view (`request()->path()`) and a separate frontend know about a page; route names would serve only the former. Paths are normalised before they are stored or looked up, and uniqueness is checked on the normalised form.

**Page values fall back field by field to the site's.** An empty field means "like the site", including index and follow, which are three-state (yes, no, inherit).

**`manage_seo` is a custom permission for Site SEO; pages use Shield's resource permissions.** The same split as Settings versus a resource.

**Contact and Social media tabs were removed.** They were added in Phase 10, never released, and are not in the requested list. A settings migration moves the contact email back to General, where it is again, and drops the others.

**Email settings apply when the mailer is first built, not at boot.** `resolving('mail.manager')` reads them, which covers web requests, queue workers and commands without touching the database for processes that send no mail, and without failing during an install when there is no database yet. Off (the default), `.env` stays in charge. A long-running worker needs `queue:restart` to see a change, as with any configuration.

**The test email uses the values in the form, saved or not.** Trying new SMTP settings before they replace working ones is the point of the button. The mail configuration is restored and the mailers rebuilt afterwards, success or failure.

**Secrets never reach the browser.** A tab lists its secrets; the page leaves them out of the form, an empty field keeps the stored value, and the field is emptied again after saving. They are also stored encrypted (spatie/laravel-settings `encrypted()`) and masked in the activity log by `SensitiveData`, which already matched `password` and `secret`.

**Social sign-in is token-based, for the API's end users.** The kit's users sign in through a separate frontend, so the frontend runs the provider's flow and sends the access token; the server asks the provider whom it belongs to (`userFromToken`). No redirect URLs, sessions or state to configure. Admins do not sign in this way: the panel keeps passwords with MFA.

**laravel/socialite was added.** Asked for by name. 5.32 requires `illuminate/* ^12|^13` and PHP ^8.2, which the matrix satisfies; `composer audit` reports nothing.

**An existing account is joined only on an address the provider vouches for.** Otherwise anyone could open a provider account under somebody else's address and sign in as them. Google and LinkedIn report `email_verified` per address; GitHub (primary verified address) and Facebook only return confirmed addresses; X never says, so it never joins an existing account. The same rule decides whether a new account's address counts as verified.

**Providers are enabled only with both credentials.** The switch alone could advertise a provider that fails on every attempt; `GET /api/v1/settings` lists only usable ones.

## Deviations from the brief

| Brief | What the kit does | Why |
|---|---|---|
| README.md is already in the repository | It was written from the brief | The working directory was empty |
| Pest 4 | Pest 5.3 | Current major; every plugin resolves against Laravel 13 and Livewire 4 |
| Resources for admins and roles | A `Users` resource as well | The repository pattern is required for `User`; a resource makes it usable and gives the limited-admin scenario something to be limited to |
| `laravel new --using` as a one-step entry point | Two steps: the Laravel installer, then `php artisan dash:install` | The Laravel installer runs a kit's hooks without a terminal |
| Installer options as listed | Also `--skip-testing-db`, `--no-serve`, `--testing-db`, `--assets`, `--git` | Needed to make "skipped in production unless asked" and unattended runs expressible |
| Every user-facing string translated | Command-line output is English only | Installer and doctor output is read by developers and pasted into issues |
| Environment variables as listed | Also `TRUSTED_PROXIES`, `TRUSTED_HOSTS`, `BACKUP_ARCHIVE_PASSWORD`, `HEALTH_NOTIFICATIONS_ENABLED`, `HEALTH_TO_ADDRESS` | See above and Phase 6 |
| `dash:doctor` checks as listed | One more: host names | A wrong `APP_URL` outside local now means a 400 for everyone, and needs to be easy to diagnose |
| Packages as listed | Also laravel/socialite | Social sign-in was asked for by name |
| Guard rails as listed | Two more: no assigning roles beyond your own permissions, no managing admins with more access | Without them `update:admin` was a path to nearly every permission |

## Known TODOs

- Tag `v1.0.0` on `main` (GitHub: Releases, Draft a new release), then submit `https://github.com/omarkhaled001/ghost-dash` to Packagist. Until both are done `composer create-project omarkhaled001/ghost-dash` and the one-line commands cannot find a stable package; a git clone followed by `composer setup` works today.
- Add a `LICENSE` file. `composer.json` and the README say MIT, but the copyright holder is the owner's to name.
- No Content-Security-Policy is sent (see Phase 1). Add one per project, with nonces.
- The API covers authentication only. Profile updates, account deletion and token listing are left to the project.
