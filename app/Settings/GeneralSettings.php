<?php

declare(strict_types=1);

namespace App\Settings;

use App\Settings\Contracts\PublicSettings;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\Settings;

final class GeneralSettings extends Settings implements PublicSettings
{
    public string $app_name;

    public ?string $logo_path = null;

    public ?string $favicon_path = null;

    public string $default_locale;

    /**
     * Display time zone for the panel. Dates are always stored in UTC.
     */
    public string $timezone;

    public static function group(): string
    {
        return 'general';
    }

    public static function publicUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }

    public function toPublicArray(): array
    {
        return [
            'app_name' => $this->app_name,
            'logo_url' => self::publicUrl($this->logo_path),
            'favicon_url' => self::publicUrl($this->favicon_path),
            'default_locale' => $this->default_locale,
        ];
    }
}
