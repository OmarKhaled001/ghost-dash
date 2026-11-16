<?php

declare(strict_types=1);

namespace App\Filament\Settings\Tabs;

use App\Filament\Settings\SettingsTab;
use App\Settings\SocialSettings;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Override;

/**
 * @extends SettingsTab<SocialSettings>
 */
final class SocialTab extends SettingsTab
{
    private const array NETWORKS = ['facebook', 'instagram', 'x', 'linkedin', 'youtube', 'tiktok', 'snapchat'];

    public static function settings(): string
    {
        return SocialSettings::class;
    }

    public function label(): string
    {
        return __('settings/social.label');
    }

    #[Override]
    public function icon(): Heroicon
    {
        return Heroicon::OutlinedShare;
    }

    #[Override]
    public function sort(): int
    {
        return 30;
    }

    public function components(): array
    {
        return [
            Section::make()
                ->description(__('settings/social.description'))
                ->columns(2)
                ->schema(array_map(
                    fn (string $network): TextInput => TextInput::make($network)
                        ->label(__("settings/social.fields.{$network}"))
                        ->url()
                        // Only links to the web: a javascript: or data: URL
                        // would run when someone clicks it on the website.
                        ->startsWith(['https://', 'http://'])
                        ->maxLength(500),
                    self::NETWORKS,
                )),
        ];
    }
}
