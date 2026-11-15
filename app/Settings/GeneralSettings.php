<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class GeneralSettings extends Settings
{
    public string $app_name;

    public ?string $logo_path = null;

    public ?string $favicon_path = null;

    public string $default_locale;

    /**
     * Display time zone for the panel. Dates are always stored in UTC.
     */
    public string $timezone;

    public ?string $contact_email = null;

    public static function group(): string
    {
        return 'general';
    }
}
