<?php

declare(strict_types=1);

namespace App\Filament\Settings\Tabs;

use App\Enums\ThemeColor;
use App\Filament\Settings\SettingsTab;
use App\Settings\AppearanceSettings;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Override;

/**
 * @extends SettingsTab<AppearanceSettings>
 */
final class AppearanceTab extends SettingsTab
{
    public static function settings(): string
    {
        return AppearanceSettings::class;
    }

    public function label(): string
    {
        return __('settings/appearance.label');
    }

    #[Override]
    public function icon(): Heroicon
    {
        return Heroicon::OutlinedSwatch;
    }

    #[Override]
    public function sort(): int
    {
        return 40;
    }

    public function components(): array
    {
        return [
            Section::make()
                ->schema([
                    Select::make('primary_color')
                        ->label(__('settings/appearance.fields.primary_color'))
                        ->helperText(__('settings/appearance.hints.primary_color'))
                        ->options(ThemeColor::options())
                        ->rule(Rule::enum(ThemeColor::class))
                        ->required()
                        ->native(false),
                ]),
        ];
    }
}
