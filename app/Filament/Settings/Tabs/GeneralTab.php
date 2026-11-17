<?php

declare(strict_types=1);

namespace App\Filament\Settings\Tabs;

use App\Filament\Settings\SettingsTab;
use App\Filament\Support\RasterImage;
use App\Settings\GeneralSettings;
use App\Support\Locales;
use DateTimeZone;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Override;

/**
 * @extends SettingsTab<GeneralSettings>
 */
final class GeneralTab extends SettingsTab
{
    public static function settings(): string
    {
        return GeneralSettings::class;
    }

    public function label(): string
    {
        return __('settings/general.label');
    }

    #[Override]
    public function icon(): Heroicon
    {
        return Heroicon::OutlinedCog6Tooth;
    }

    #[Override]
    public function sort(): int
    {
        return 10;
    }

    public function components(): array
    {
        return [
            Section::make(__('settings/general.sections.identity'))
                ->columns(2)
                ->schema([
                    TextInput::make('app_name')
                        ->label(__('settings/general.fields.app_name'))
                        ->required()
                        ->maxLength(100)
                        ->columnSpanFull(),

                    FileUpload::make('logo_path')
                        ->label(__('settings/general.fields.logo_path'))
                        ->helperText(__('settings/general.hints.logo_path'))
                        ->image()
                        ->acceptedFileTypes(RasterImage::MIME_TYPES)
                        // Settings are not a model, so Filament cannot tell
                        // on its own which stored path is the current one.
                        // Anything else must be an upload.
                        ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => $file === $this->values()->logo_path)
                        ->disk('public')
                        ->directory('branding')
                        ->visibility('public')
                        ->maxSize(1024),

                    FileUpload::make('favicon_path')
                        ->label(__('settings/general.fields.favicon_path'))
                        ->helperText(__('settings/general.hints.favicon_path'))
                        ->image()
                        ->acceptedFileTypes(RasterImage::MIME_TYPES)
                        ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => $file === $this->values()->favicon_path)
                        ->disk('public')
                        ->directory('branding')
                        ->visibility('public')
                        ->maxSize(512),
                ]),

            Section::make(__('settings/general.sections.regional'))
                ->columns(2)
                ->schema([
                    Select::make('default_locale')
                        ->label(__('settings/general.fields.default_locale'))
                        ->helperText(__('settings/general.hints.default_locale'))
                        ->options(Locales::options())
                        ->required()
                        ->native(false),

                    Select::make('timezone')
                        ->label(__('settings/general.fields.timezone'))
                        ->helperText(__('settings/general.hints.timezone'))
                        ->options(fn (): array => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                        ->searchable()
                        ->required(),

                    TextInput::make('contact_email')
                        ->label(__('settings/general.fields.contact_email'))
                        ->email()
                        ->maxLength(255),
                ]),
        ];
    }
}
