<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Panel
    |--------------------------------------------------------------------------
    |
    | The URL segment the Filament panel is served from. Changing it to
    | something unguessable is cheap obscurity, not security: every page
    | behind it is still protected by the admin guard and its policies.
    |
    */

    'panel' => [
        'path' => trim((string) env('ADMIN_PANEL_PATH', 'admin'), '/') ?: 'admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    |
    | Languages offered by the panel language switcher and accepted from the
    | API's Accept-Language header. Each one needs a lang/<code> directory.
    |
    */

    'locales' => ['en', 'ar'],

    /*
    |--------------------------------------------------------------------------
    | Frontend
    |--------------------------------------------------------------------------
    |
    | Where the end-user application lives. Password reset emails sent by
    | the API link here rather than to this backend.
    |
    */

    'frontend_url' => rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Trusted hosts
    |--------------------------------------------------------------------------
    |
    | Outside the local environment the application only answers requests
    | whose Host header is APP_URL's host or one of its subdomains. Anything
    | else gets a 400. List further host names here, comma-separated, as
    | regular expressions: a second domain ("^api\.example\.org$"), or the
    | private addresses a load balancer uses for health checks ("^10\.").
    |
    */

    'trusted_hosts' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('TRUSTED_HOSTS', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Settings tabs
    |--------------------------------------------------------------------------
    |
    | Every SettingsTab in app/Filament/Settings/Tabs appears on the Settings
    | page by itself. List here only tabs that live somewhere else, such as
    | in a package. `php artisan make:settings-tab Name` creates a new one.
    |
    */

    'settings' => [
        'tabs' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    |
    | Any attribute or property whose key contains one of these fragments is
    | stored as "[redacted]", whichever model or caller logged it. Matching
    | is case-insensitive and applies at every nesting level.
    |
    */

    'activity_log' => [
        'redact' => [
            'password',
            'secret',
            'token',
            'recovery_code',
            'authorization',
            'cookie',
            'api_key',
        ],
    ],

];
