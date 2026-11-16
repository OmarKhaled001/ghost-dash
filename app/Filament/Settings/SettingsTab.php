<?php

declare(strict_types=1);

namespace App\Filament\Settings;

use App\Enums\CustomPermission;
use BackedEnum;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Spatie\LaravelSettings\Settings;

/**
 * One tab of the Settings page, backed by one spatie/laravel-settings class.
 *
 * Every concrete subclass in app/Filament/Settings/Tabs appears on the page
 * by itself. Create one with `php artisan make:settings-tab Name`.
 *
 * @template TSettings of Settings
 */
abstract class SettingsTab
{
    /**
     * @return class-string<TSettings>
     */
    abstract public static function settings(): string;

    abstract public function label(): string;

    /**
     * The form fields, named after the settings class's properties.
     *
     * @return list<Component>
     */
    abstract public function components(): array;

    public function icon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedCog6Tooth;
    }

    /**
     * Tabs are shown in ascending order, ties by label.
     */
    public function sort(): int
    {
        return 100;
    }

    /**
     * Who may see and change this tab. Opening the Settings page at all
     * takes manage_settings; a tab can ask for a narrower permission on top.
     */
    public function permission(): string
    {
        return CustomPermission::ManageSettings->value;
    }

    /**
     * Also the tab's name in the URL (?tab=general) and the key its values
     * sit under in the form.
     */
    final public function key(): string
    {
        return static::settings()::group();
    }

    /**
     * @return TSettings
     */
    final public function values(): Settings
    {
        return resolve(static::settings());
    }
}
