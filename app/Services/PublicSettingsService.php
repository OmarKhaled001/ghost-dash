<?php

declare(strict_types=1);

namespace App\Services;

use App\Settings\Contracts\PublicSettings;
use App\Support\Discovery;
use Spatie\LaravelSettings\Settings;

final readonly class PublicSettingsService
{
    /**
     * What every settings class in app/Settings chooses to make public,
     * keyed by its group: general, contact, social, seo, and so on.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $public = [];

        foreach (Discovery::classes(app_path('Settings'), app()->getNamespace().'Settings', PublicSettings::class) as $class) {
            if (! is_subclass_of($class, Settings::class)) {
                continue;
            }

            /** @var Settings&PublicSettings $settings */
            $settings = resolve($class);

            $public[$settings::group()] = $settings->toPublicArray();
        }

        ksort($public);

        return $public;
    }
}
