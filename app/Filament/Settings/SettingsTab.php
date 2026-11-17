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
     * Properties that never travel to the browser: the form starts empty for
     * them, and leaving one empty keeps what is stored. Declare the same
     * properties in the settings class's encrypted() so they are stored
     * encrypted as well.
     *
     * @return list<string>
     */
    public function secrets(): array
    {
        return [];
    }

    /**
     * The values the form starts with.
     *
     * @return array<string, mixed>
     */
    final public function formValues(): array
    {
        return array_diff_key($this->values()->toArray(), array_flip($this->secrets()));
    }

    /**
     * The submitted values to store: an empty secret means "unchanged".
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    final public function valuesToSave(array $state): array
    {
        foreach ($this->secrets() as $secret) {
            if (blank($state[$secret] ?? null)) {
                unset($state[$secret]);
            }
        }

        return $state;
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
