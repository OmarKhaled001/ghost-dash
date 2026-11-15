<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\ActivityLog;
use App\Enums\CustomPermission;
use App\Filament\Concerns\AuthorizesWithPermission;
use App\Filament\Contracts\GatedByPermission;
use App\Filament\Support\RasterImage;
use App\Settings\GeneralSettings;
use App\Support\Locales;
use BackedEnum;
use DateTimeZone;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Override;

final class ManageGeneralSettings extends SettingsPage implements GatedByPermission
{
    use AuthorizesWithPermission;

    protected static string $settings = GeneralSettings::class;

    protected static ?string $slug = 'settings/general';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 50;

    /**
     * Settings as they were when the form was loaded, to log what changed.
     *
     * @var array<string, mixed>
     */
    private array $before = [];

    public static function gatePermission(): string
    {
        return CustomPermission::ManageSettings->value;
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('admin.navigation.system');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('admin.settings.title');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('admin.settings.title');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.settings.sections.identity'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('app_name')
                            ->label(__('admin.settings.fields.app_name'))
                            ->required()
                            ->maxLength(100)
                            ->columnSpanFull(),

                        FileUpload::make('logo_path')
                            ->label(__('admin.settings.fields.logo'))
                            ->helperText(__('admin.settings.hints.logo'))
                            ->image()
                            ->acceptedFileTypes(RasterImage::MIME_TYPES)
                            // Settings are not a model, so Filament cannot
                            // tell on its own which stored path is the
                            // current one. Anything else must be an upload.
                            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => $file === resolve(GeneralSettings::class)->logo_path)
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->maxSize(1024),

                        FileUpload::make('favicon_path')
                            ->label(__('admin.settings.fields.favicon'))
                            ->helperText(__('admin.settings.hints.favicon'))
                            ->image()
                            ->acceptedFileTypes(RasterImage::MIME_TYPES)
                            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => $file === resolve(GeneralSettings::class)->favicon_path)
                            ->disk('public')
                            ->directory('branding')
                            ->visibility('public')
                            ->maxSize(512),
                    ]),

                Section::make(__('admin.settings.sections.regional'))
                    ->columns(2)
                    ->schema([
                        Select::make('default_locale')
                            ->label(__('admin.settings.fields.default_locale'))
                            ->helperText(__('admin.settings.hints.default_locale'))
                            ->options(Locales::options())
                            ->required()
                            ->native(false),

                        Select::make('timezone')
                            ->label(__('admin.settings.fields.timezone'))
                            ->helperText(__('admin.settings.hints.timezone'))
                            ->options(fn (): array => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                            ->searchable()
                            ->required(),

                        TextInput::make('contact_email')
                            ->label(__('admin.settings.fields.contact_email'))
                            ->email()
                            ->maxLength(255),
                    ]),
            ]);
    }

    protected function beforeSave(): void
    {
        $this->before = resolve(GeneralSettings::class)->toArray();
    }

    protected function afterSave(): void
    {
        $after = resolve(GeneralSettings::class)->toArray();

        $changed = array_keys(array_filter(
            $after,
            fn (mixed $value, string $key): bool => ($this->before[$key] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($changed === []) {
            return;
        }

        activity(ActivityLog::Settings)
            ->event('updated')
            ->withChanges([
                'old' => array_intersect_key($this->before, array_flip($changed)),
                'attributes' => array_intersect_key($after, array_flip($changed)),
            ])
            ->log('updated');
    }
}
