<?php

declare(strict_types=1);

namespace App\Filament\Settings\Tabs;

use App\Enums\SocialProvider;
use App\Filament\Settings\SettingsTab;
use App\Settings\SocialiteSettings;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Override;

/**
 * Sign-in with external providers for end users of the API.
 *
 * @extends SettingsTab<SocialiteSettings>
 */
final class SocialiteTab extends SettingsTab
{
    public static function settings(): string
    {
        return SocialiteSettings::class;
    }

    public function label(): string
    {
        return __('settings/socialite.label');
    }

    #[Override]
    public function icon(): Heroicon
    {
        return Heroicon::OutlinedKey;
    }

    #[Override]
    public function sort(): int
    {
        return 40;
    }

    #[Override]
    public function secrets(): array
    {
        return SocialiteSettings::encrypted();
    }

    public function components(): array
    {
        return array_map($this->provider(...), SocialProvider::cases());
    }

    private function provider(SocialProvider $provider): Section
    {
        $key = $provider->value;
        $enabled = fn (Get $get): bool => (bool) $get("{$key}_enabled");

        return Section::make($provider->label())
            ->description(__('settings/socialite.hints.endpoint', ['url' => url("/api/v1/auth/social/{$key}")]))
            ->collapsible()
            ->collapsed(fn (): bool => ! $this->values()->{"{$key}_enabled"})
            ->columns(2)
            ->schema([
                Toggle::make("{$key}_enabled")
                    ->label(__('settings/socialite.fields.enabled'))
                    ->live()
                    ->columnSpanFull(),

                TextInput::make("{$key}_client_id")
                    ->label(__('settings/socialite.fields.client_id'))
                    ->maxLength(500)
                    ->required($enabled),

                TextInput::make("{$key}_client_secret")
                    ->label(__('settings/socialite.fields.client_secret'))
                    ->helperText(__('settings/socialite.hints.client_secret'))
                    ->password()
                    ->maxLength(500)
                    ->autocomplete('new-password')
                    ->placeholder(fn (): ?string => filled($this->values()->clientSecret($provider)) ? '••••••••' : null)
                    // Required only while nothing is stored: an empty field
                    // keeps the saved secret.
                    ->required(fn (Get $get): bool => $enabled($get) && blank($this->values()->clientSecret($provider))),
            ]);
    }
}
