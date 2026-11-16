<?php

declare(strict_types=1);

namespace App\Filament\Settings\Tabs;

use App\Filament\Settings\SettingsTab;
use App\Settings\ContactSettings;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Override;

/**
 * @extends SettingsTab<ContactSettings>
 */
final class ContactTab extends SettingsTab
{
    public static function settings(): string
    {
        return ContactSettings::class;
    }

    public function label(): string
    {
        return __('settings/contact.label');
    }

    #[Override]
    public function icon(): Heroicon
    {
        return Heroicon::OutlinedPhone;
    }

    #[Override]
    public function sort(): int
    {
        return 20;
    }

    public function components(): array
    {
        return [
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('email')
                        ->label(__('settings/contact.fields.email'))
                        ->email()
                        ->maxLength(255),

                    TextInput::make('phone')
                        ->label(__('settings/contact.fields.phone'))
                        ->tel()
                        ->maxLength(30),

                    TextInput::make('whatsapp')
                        ->label(__('settings/contact.fields.whatsapp'))
                        ->helperText(__('settings/contact.hints.whatsapp'))
                        ->regex('/^[1-9][0-9]{6,14}$/')
                        ->maxLength(15),

                    TextInput::make('map_url')
                        ->label(__('settings/contact.fields.map_url'))
                        ->url()
                        ->maxLength(500),

                    Textarea::make('address')
                        ->label(__('settings/contact.fields.address'))
                        ->rows(3)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),
        ];
    }
}
