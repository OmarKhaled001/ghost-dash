# Changelog

All notable changes to GhostDash are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- A tabbed Settings page at `/admin/settings`, replacing the single General settings page. Tabs are discovered from `app/Filament/Settings/Tabs`, each backed by its own settings class, and may require a narrower permission than `manage_settings`.
- New default tabs: Contact (email, phone, WhatsApp, address, map link), Social media (seven networks) and Appearance (the panel's primary colour).
- `php artisan make:settings-tab Name`, which writes a settings class, its migration, the tab and both translation files.
- `php artisan dash:seo` and `dash:install --seo`: an SEO tab (titles, description, sharing image, indexing, verification codes, Google Analytics and Tag Manager) and an `<x-seo>` component for page heads.
- `GET /api/v1/settings`: the settings each group chooses to make public, for a separate frontend.
- `SettingsService`, through which every settings change is saved and logged, one activity entry per group.

### Changed

- The contact email moved from the General group to the Contact tab (`contact.email`). A settings migration moves the stored value; run `php artisan migrate` when upgrading.
- Settings translations moved to `lang/{en,ar}/settings/<group>.php`.

## [1.0.0] - 2026-11-15

### Added

- Laravel 13 skeleton targeting PHP 8.4+ and MySQL 8.4 LTS only, with session, cache and queue on the `database` driver.
- Application defaults: strict Eloquent models outside production, destructive database commands prohibited in production, immutable dates, forced HTTPS in production, a 12-character mixed-case password policy.
- `SecurityHeaders` middleware.
- Tooling: Pest 5, Larastan level 6 with no baseline, Pint (Laravel preset plus strict rules), Rector with rector-laravel, and `composer check`.
- `ci.yml` GitHub Actions workflow: MySQL 8.4 service, PHP 8.4 and 8.5.
- `Admin` model on its own `admin` guard, provider and `admins` password broker, with a dedicated reset-token table.
- Filament 5 panel at `ADMIN_PANEL_PATH` with login, password reset, email verification, email-change verification, profile page and multi-factor authentication (authenticator app with recovery codes, and email codes).
- `is_active` flag: inactive admins cannot sign in and are signed out on their next request by `EnsureAdminIsActive`. Last login time and IP are recorded.
- Roles and permissions with Filament Shield on the `admin` guard, a `super_admin` role, generated policies, and custom `Role` / `Permission` models that default to that guard.
- `AdminResource`, `RoleResource` and `UserResource`, with every write routed through `AdminService`, `RoleService` and `UserService`, and data access through repository interfaces bound in `RepositoryServiceProvider`.
- Guard rails enforced in services and policies: no self-delete or self-deactivate, the last active super admin cannot be removed or demoted, only a super admin manages super admins, super admins are invisible to other admins, and a role editor cannot grant permissions they do not hold.
- Custom permissions `view_telescope`, `view_activity_log`, `manage_settings`, `manage_backups`, `view_health`.
- `PermissionSyncService`, which creates missing permissions and grants them to `super_admin` without writing files.
- Authorization tests that discover every registered panel resource instead of listing them.
- Activity log (spatie/laravel-activitylog 5): model events on `Admin`, `User` and `Role`, explicit logging of admin-role and role-permission changes, and authentication events for both guards with IP and user agent.
- Causer resolution across guards (admin session, Sanctum token, web session) and global redaction of sensitive values through a custom `LogActivity` action.
- Read-only `ActivityResource` with filters, a translated before/after diff and a reusable `ActivitiesRelationManager` timeline, gated by `view_activity_log`.
- Nightly `activitylog:clean` using `ACTIVITYLOG_RETENTION_DAYS`.
- A morph map for `Admin`, `User`, `Role` and `Permission`.
- Laravel Telescope in `require`, loaded only when `TELESCOPE_ENABLED=true`. Outside `local` it is reachable only by active admins holding `view_telescope`, guests are redirected to the panel login, sensitive request data is hidden, and only exceptions, failed requests, failed jobs, slow queries and tagged entries are recorded. Pruned daily; linked from the panel navigation.
- Language switcher (Arabic / English, RTL) that stores the choice in the session, a cookie and on the admin record; Arabic translations of Laravel's validation, auth, password and pagination messages.
- General settings page (spatie/laravel-settings): app name, logo, favicon, default language, time zone, contact email. Gated by `manage_settings`.
- Backups (spatie/laravel-backup): scheduled MySQL dump plus `storage/app/public`, cleanup and monitoring, optional mail notifications, and a Backups page to create, download and delete archives. Gated by `manage_backups`.
- Health checks (spatie/laravel-health) with a Health page. Gated by `view_health`.
- Laravel Boost with generated AI guidelines (`AGENTS.md`), including the kit's own conventions from `.ai/guidelines/ghostdash.md`.
- `make:service` and `make:repository` generators, and Laravel's own stubs republished with strict types and final classes.
- Sanctum API for end users under `/api/v1/auth/*`: register, login, logout, logout-all, me, forgot-password, reset-password, change password, verify email and resend verification, with Form Requests, an API Resource and a named rate limiter per endpoint.
- One JSON envelope for every API response, including validation errors, 401, 403, 404, 405, 429 and 500.
- Tokens with abilities (`account:read`, `account:write`) and an expiry from `SANCTUM_TOKEN_EXPIRATION`.
- `SetLocaleFromHeader`: API messages, validation errors and emails follow `Accept-Language` (Arabic or English).
- `php artisan dash:install`: an idempotent, resumable installer (environment, MySQL connection with error-specific hints, testing database, schema and permissions, first super admin, demo data, assets, git, summary), usable interactively or fully unattended.
- `php artisan dash:doctor` and `php artisan dash:sync-permissions`.
- One-command bootstrap scripts `install.sh` (macOS, Linux, WSL) and `install.ps1` (Windows PowerShell 5.1 and PowerShell 7).
- `install-e2e.yml`: runs the bootstrap scripts against the current commit on Ubuntu and Windows, serves the panel and signs the super admin in over HTTP.
- `TRUSTED_PROXIES`, for deployments behind a load balancer or reverse proxy.
- Image uploads (admin avatars, logo, favicon) accept PNG, JPEG and WebP only.
- Nobody can assign a role carrying permissions they lack, or edit, deactivate or delete an admin who holds permissions they lack.
- The activity log leaves out everything done by or to a super admin for viewers who are not super admins.
- Telescope masks sensitive values by key name at any depth, including inside Livewire payloads and API responses.
- API: a per-IP cap on sign-in and password reset requests, spellings the database treats as one address share one limit, a lockout is logged once per minute, `logout-all` requires `account:write`, reset mails are sent after the response, and an admin setting a user's password revokes that user's tokens.
- Outside `local`, requests are answered only for the host in `APP_URL`, its subdomains and `TRUSTED_HOSTS`. `dash:doctor` reports the host names in effect.
- Upload fields refuse stored paths other than their own, and the bootstrap scripts no longer echo password options.
- A cover image for the README (`.github/art/cover-en.jpg`), composed from real screenshots of the panel in English and Arabic.
