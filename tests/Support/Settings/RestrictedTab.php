<?php

declare(strict_types=1);

namespace Tests\Support\Settings;

use App\Enums\CustomPermission;
use App\Filament\Settings\SettingsTab;
use Filament\Forms\Components\TextInput;
use Override;

/**
 * @extends SettingsTab<RestrictedSettings>
 */
final class RestrictedTab extends SettingsTab
{
    public static function settings(): string
    {
        return RestrictedSettings::class;
    }

    public function label(): string
    {
        return 'Restricted';
    }

    #[Override]
    public function permission(): string
    {
        return CustomPermission::ViewHealth->value;
    }

    public function components(): array
    {
        return [TextInput::make('value')];
    }
}
