<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\CustomPermission;
use App\Filament\Contracts\GatedByPermission;
use App\Filament\Support\RasterImage;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Settings\SeoSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Override;

/**
 * Site-wide SEO: the defaults every page starts from. Pages with their own
 * SEO are in SeoPageResource, next to this in the SEO menu.
 *
 * @property-read Schema $form
 */
final class ManageSiteSeo extends Page implements GatedByPermission
{
    use HasUnsavedDataChangesAlert;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    protected static ?string $slug = 'seo/site';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 1;

    public static function gatePermission(): string
    {
        return CustomPermission::ManageSeo->value;
    }

    /**
     * Off, the page is neither in the menu nor reachable by URL.
     */
    #[Override]
    public static function canAccess(): bool
    {
        return SeoService::enabled()
            && (Filament::auth()->user()?->can(self::gatePermission()) ?? false);
    }

    #[Override]
    public static function getNavigationGroup(): string
    {
        return __('seo.navigation');
    }

    #[Override]
    public static function getNavigationLabel(): string
    {
        return __('seo.site.title');
    }

    #[Override]
    public function getTitle(): string
    {
        return __('seo.site.title');
    }

    public function mount(): void
    {
        $this->form->fill(resolve(SeoSettings::class)->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('seo.sections.search'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('site_name')
                            ->label(__('seo.fields.site_name'))
                            ->helperText(__('seo.hints.site_name'))
                            ->maxLength(100),

                        Select::make('title_separator')
                            ->label(__('seo.fields.title_separator'))
                            ->options(['|' => '|', '-' => '-', '–' => '–', '·' => '·', '•' => '•', '/' => '/'])
                            ->required()
                            ->native(false),

                        Textarea::make('description')
                            ->label(__('seo.fields.default_description'))
                            ->helperText(__('seo.hints.description'))
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),

                        TextInput::make('keywords')
                            ->label(__('seo.fields.keywords'))
                            ->helperText(__('seo.hints.keywords'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('seo.sections.sharing'))
                    ->columns(2)
                    ->schema([
                        FileUpload::make('og_image_path')
                            ->label(__('seo.fields.og_image_path'))
                            ->helperText(__('seo.hints.og_image_path'))
                            ->image()
                            ->acceptedFileTypes(RasterImage::MIME_TYPES)
                            // Settings are not a model, so Filament cannot tell
                            // on its own which stored path is the current one.
                            ->preventFilePathTampering(allowFilePathUsing: fn (string $file): bool => $file === resolve(SeoSettings::class)->og_image_path)
                            ->disk('public')
                            ->directory('seo')
                            ->visibility('public')
                            ->maxSize(2048),

                        TextInput::make('twitter_handle')
                            ->label(__('seo.fields.twitter_handle'))
                            ->placeholder('@yourname')
                            ->regex('/^@[A-Za-z0-9_]{1,15}$/'),
                    ]),

                Section::make(__('seo.sections.indexing'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('index')
                            ->label(__('seo.fields.index'))
                            ->helperText(__('seo.hints.index')),

                        Toggle::make('follow')
                            ->label(__('seo.fields.follow'))
                            ->helperText(__('seo.hints.follow')),
                    ]),

                Section::make(__('seo.sections.tools'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('google_site_verification')
                            ->label(__('seo.fields.google_site_verification'))
                            ->regex('/^[A-Za-z0-9_-]{10,100}$/'),

                        TextInput::make('bing_site_verification')
                            ->label(__('seo.fields.bing_site_verification'))
                            ->regex('/^[A-Za-z0-9]{10,64}$/'),

                        TextInput::make('google_analytics_id')
                            ->label(__('seo.fields.google_analytics_id'))
                            ->placeholder('G-XXXXXXXXXX')
                            ->regex('/^G-[A-Z0-9]{4,20}$/'),

                        TextInput::make('google_tag_manager_id')
                            ->label(__('seo.fields.google_tag_manager_id'))
                            ->placeholder('GTM-XXXXXXX')
                            ->regex('/^GTM-[A-Z0-9]{4,12}$/'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('admin.settings.save'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(SettingsService $settings): void
    {
        $settings->update([SeoSettings::class => $this->form->getState()]);

        $this->rememberData();

        Notification::make()
            ->success()
            ->title(__('admin.settings.saved'))
            ->send();
    }
}
