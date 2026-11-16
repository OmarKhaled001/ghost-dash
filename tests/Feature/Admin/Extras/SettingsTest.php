<?php

declare(strict_types=1);

use App\Enums\ThemeColor;
use App\Filament\Pages\ManageSettings;
use App\Filament\Settings\SettingsTab;
use App\Filament\Settings\SettingsTabs;
use App\Models\Activity;
use App\Settings\AppearanceSettings;
use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;
use App\Settings\SocialSettings;
use App\Support\ActivityPresenter;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelSettings\Migrations\SettingsMigrator;
use Tests\Support\Settings\RestrictedSettings;
use Tests\Support\Settings\RestrictedTab;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    actingAsAdmin(adminWith(['manage_settings']));
});

describe('tabs', function (): void {
    it('finds every tab in app/Filament/Settings/Tabs by itself, in their order', function (): void {
        $tabs = resolve(SettingsTabs::class)->all();

        // Every file in the directory, including tabs a project added.
        expect($tabs)->toHaveCount(count(glob(app_path('Filament/Settings/Tabs/*Tab.php'))));

        // The kit's own tabs come first, in this order.
        expect($tabs->take(4)->map(fn (SettingsTab $tab): string => $tab->key())->all())
            ->toBe(['general', 'contact', 'social', 'appearance'])
            ->and($tabs->map(fn (SettingsTab $tab): int => $tab->sort())->all())
            ->toBe($tabs->map(fn (SettingsTab $tab): int => $tab->sort())->sort()->values()->all());
    });

    it('shows a tab per settings group', function (): void {
        livewire(ManageSettings::class)
            ->assertOk()
            ->assertSee([__('settings/general.label'), __('settings/contact.label'), __('settings/social.label'), __('settings/appearance.label')]);
    });

    it('also takes tabs listed in ghostdash.settings.tabs', function (): void {
        config(['ghostdash.settings.tabs' => [RestrictedTab::class]]);

        expect(resolve(SettingsTabs::class)->all()->map(fn (SettingsTab $tab): string => $tab->key()))->toContain('restricted');
    });

    it('hides a tab that needs a permission the admin lacks, and ignores a forged value for it', function (): void {
        resolve(SettingsMigrator::class)->add('restricted.value', 'original');
        config(['ghostdash.settings.tabs' => [RestrictedTab::class]]);

        livewire(ManageSettings::class)
            ->assertDontSee('Restricted')
            ->set('data.restricted.value', 'forged')
            ->call('save')
            ->assertHasNoFormErrors();

        expect(resolve(RestrictedSettings::class)->refresh()->value)->toBe('original');

        actingAsAdmin(adminWith(['manage_settings', 'view_health']));

        livewire(ManageSettings::class)
            ->assertSee('Restricted')
            ->set('data.restricted.value', 'changed')
            ->call('save')
            ->assertHasNoFormErrors();

        expect(resolve(RestrictedSettings::class)->refresh()->value)->toBe('changed');
    });
});

describe('defaults', function (): void {
    it('ships sensible defaults', function (): void {
        $general = resolve(GeneralSettings::class);

        expect($general->app_name)->not->toBeEmpty()
            ->and($general->default_locale)->toBe('en')
            ->and($general->timezone)->toBe('UTC')
            ->and($general->logo_path)->toBeNull()
            ->and(resolve(ContactSettings::class)->email)->toBeNull()
            ->and(resolve(SocialSettings::class)->toPublicArray())->toBe([])
            ->and(resolve(AppearanceSettings::class)->primaryColor())->toBe(ThemeColor::Indigo);
    });

    it('moved the contact address out of the general group', function (): void {
        expect(DB::table('settings')->where('group', 'general')->where('name', 'contact_email')->exists())->toBeFalse()
            ->and(DB::table('settings')->where('group', 'contact')->where('name', 'email')->exists())->toBeTrue();
    });
});

describe('the form', function (): void {
    it('fills every tab with the stored settings', function (): void {
        livewire(ManageSettings::class)
            ->assertSet('data.general.app_name', resolve(GeneralSettings::class)->app_name)
            ->assertSet('data.general.timezone', 'UTC')
            ->assertSet('data.appearance.primary_color', 'indigo');
    });

    it('saves every tab at once', function (): void {
        livewire(ManageSettings::class)
            ->set('data.general.app_name', 'Acme Console')
            ->set('data.general.default_locale', 'ar')
            ->set('data.general.timezone', 'Africa/Cairo')
            ->set('data.contact.email', 'ops@example.com')
            ->set('data.contact.whatsapp', '201001234567')
            ->set('data.social.facebook', 'https://facebook.com/acme')
            ->set('data.appearance.primary_color', 'emerald')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.settings.saved'));

        $general = resolve(GeneralSettings::class)->refresh();

        expect($general->app_name)->toBe('Acme Console')
            ->and($general->default_locale)->toBe('ar')
            ->and($general->timezone)->toBe('Africa/Cairo')
            ->and(resolve(ContactSettings::class)->refresh()->email)->toBe('ops@example.com')
            ->and(resolve(SocialSettings::class)->refresh()->toPublicArray())->toBe(['facebook' => 'https://facebook.com/acme'])
            ->and(resolve(AppearanceSettings::class)->refresh()->primaryColor())->toBe(ThemeColor::Emerald);
    });

    it('validates every tab', function (string $field, mixed $value): void {
        livewire(ManageSettings::class)
            ->set("data.{$field}", $value)
            ->call('save')
            ->assertHasErrors(["data.{$field}"]);
    })->with([
        'name is required' => ['general.app_name', ''],
        'language must be offered' => ['general.default_locale', 'fr'],
        'time zone must exist' => ['general.timezone', 'Mars/Olympus'],
        'contact email must be an address' => ['contact.email', 'nope'],
        'whatsapp is digits with a country code' => ['contact.whatsapp', '+20 100 123'],
        'social links must be links' => ['social.facebook', 'facebook'],
        'social links cannot run script' => ['social.facebook', 'javascript:alert(1)'],
        'colour must be offered' => ['appearance.primary_color', 'chartreuse'],
    ]);
});

describe('uploads', function (): void {
    it('stores an uploaded logo on the public disk', function (): void {
        Storage::fake('public');

        livewire(ManageSettings::class)
            ->set('data.general.logo_path', UploadedFile::fake()->image('logo.png', 200, 60))
            ->call('save')
            ->assertHasNoFormErrors();

        $path = resolve(GeneralSettings::class)->refresh()->logo_path;

        expect($path)->toStartWith('branding/');
        Storage::disk('public')->assertExists($path);
    });

    it('rejects an SVG logo, which could carry script', function (): void {
        Storage::fake('public');

        livewire(ManageSettings::class)
            ->set('data.general.logo_path', UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
            ->call('save')
            ->assertHasErrors(['data.general.logo_path']);
    });

    it('keeps the stored logo on a later save, and refuses a path the browser made up', function (): void {
        Storage::fake('public');

        livewire(ManageSettings::class)
            ->set('data.general.logo_path', UploadedFile::fake()->image('logo.png', 200, 60))
            ->call('save')
            ->assertHasNoFormErrors();

        $logo = resolve(GeneralSettings::class)->refresh()->logo_path;

        livewire(ManageSettings::class)
            ->set('data.general.app_name', 'Still Has Its Logo')
            ->call('save')
            ->assertHasNoFormErrors();

        expect(resolve(GeneralSettings::class)->refresh()->logo_path)->toBe($logo);

        livewire(ManageSettings::class)
            ->set('data.general.logo_path', ['forged' => 'avatars/somebody-else.png'])
            ->call('save')
            ->assertHasErrors(['data.general.logo_path']);

        expect(resolve(GeneralSettings::class)->refresh()->logo_path)->toBe($logo);
    });
});

describe('activity log', function (): void {
    it('logs what changed per group, and nothing when nothing did', function (): void {
        livewire(ManageSettings::class)
            ->set('data.general.app_name', 'Acme Console')
            ->set('data.contact.phone', '+20 2 1234 5678')
            ->call('save');

        $activities = Activity::query()->where('log_name', 'settings')->orderBy('id')->get();

        expect($activities)->toHaveCount(2)
            ->and($activities[0]->properties->get('group'))->toBe('general')
            ->and($activities[0]->newValues())->toBe(['app_name' => 'Acme Console'])
            ->and($activities[0]->causer_type)->toBe('admin')
            ->and($activities[1]->properties->get('group'))->toBe('contact')
            ->and($activities[1]->newValues())->toBe(['phone' => '+20 2 1234 5678']);

        livewire(ManageSettings::class)->call('save');

        expect(Activity::query()->where('log_name', 'settings')->count())->toBe(2);
    });

    it('labels the changed fields with the tab\'s own translations', function (): void {
        livewire(ManageSettings::class)
            ->set('data.contact.whatsapp', '201001234567')
            ->call('save');

        $diff = ActivityPresenter::diff(Activity::query()->where('log_name', 'settings')->latest('id')->firstOrFail());

        expect($diff[0]['label'])->toBe(__('settings/contact.fields.whatsapp'));
    });
});

describe('effect on the panel', function (): void {
    it('uses the configured name as the panel brand', function (): void {
        $settings = resolve(GeneralSettings::class);
        $settings->app_name = 'Acme Console';
        $settings->save();

        get(panelUrl())->assertOk()->assertSee('Acme Console');
    });

    it('applies the default language and time zone to panel requests', function (): void {
        $settings = resolve(GeneralSettings::class);
        $settings->default_locale = 'ar';
        $settings->timezone = 'Africa/Cairo';
        $settings->save();

        get(panelUrl())->assertOk()->assertSeeHtml('dir="rtl"');

        expect(FilamentTimezone::get())->toBe('Africa/Cairo')
            ->and(config('app.locale'))->toBe('ar');
    });

    it('paints the panel in the chosen primary colour', function (): void {
        $settings = resolve(AppearanceSettings::class);
        $settings->primary_color = ThemeColor::Rose->value;
        $settings->save();

        get(panelUrl())->assertOk()->assertSeeHtml(ThemeColor::Rose->palette()[600]);
    });

    it('falls back to indigo for a colour that is no longer offered', function (): void {
        expect(ThemeColor::fromSetting('chartreuse'))->toBe(ThemeColor::Indigo);
    });
});
