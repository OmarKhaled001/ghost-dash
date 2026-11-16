<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\CustomPermission;
use App\Filament\Concerns\AuthorizesWithPermission;
use App\Filament\Contracts\GatedByPermission;
use App\Filament\Settings\SettingsTab;
use App\Filament\Settings\SettingsTabs;
use App\Filament\Support\GuardRails;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Every settings tab on one page. The tabs themselves live in
 * app/Filament/Settings/Tabs; nothing here needs to change to add one.
 *
 * @property-read Schema $form
 */
final class ManageSettings extends Page implements GatedByPermission
{
    use AuthorizesWithPermission;
    use HasUnsavedDataChangesAlert;

    /**
     * Form state, one key per tab (the settings group).
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    protected static ?string $slug = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 50;

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

    public function mount(): void
    {
        $this->form->fill(
            $this->tabs()
                ->mapWithKeys(fn (SettingsTab $tab): array => [$tab->key() => $tab->values()->toArray()])
                ->all(),
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('settings')
                    ->persistTabInQueryString()
                    ->tabs($this->tabs()->map(
                        fn (SettingsTab $tab): Tab => Tab::make($tab->label())
                            ->key($tab->key())
                            ->icon($tab->icon())
                            ->statePath($tab->key())
                            ->schema($tab->components()),
                    )->all()),
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
        // Only the tabs this admin may see are in the form, so getState()
        // carries nothing for the others, whatever the browser sent.
        $state = $this->form->getState();

        /** @var array<class-string<Settings>, array<string, mixed>> $changes */
        $changes = $this->tabs()
            ->mapWithKeys(fn (SettingsTab $tab): array => [$tab::settings() => (array) ($state[$tab->key()] ?? [])])
            ->all();

        $settings->update($changes);

        $this->rememberData();

        Notification::make()
            ->success()
            ->title(__('admin.settings.saved'))
            ->send();
    }

    /**
     * @return Collection<int, SettingsTab<Settings>>
     */
    private function tabs(): Collection
    {
        return resolve(SettingsTabs::class)->visibleTo(GuardRails::actor());
    }
}
