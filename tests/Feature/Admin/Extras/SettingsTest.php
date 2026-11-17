<?php

declare(strict_types=1);

use App\Enums\ThemeColor;
use App\Filament\Pages\ManageSettings;
use App\Filament\Settings\SettingsTab;
use App\Filament\Settings\SettingsTabs;
use App\Models\Activity;
use App\Services\MailService;
use App\Settings\AppearanceSettings;
use App\Settings\GeneralSettings;
use App\Settings\MailSettings;
use App\Settings\SocialiteSettings;
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
            ->toBe(['general', 'appearance', 'mail', 'socialite'])
            ->and($tabs->map(fn (SettingsTab $tab): int => $tab->sort())->all())
            ->toBe($tabs->map(fn (SettingsTab $tab): int => $tab->sort())->sort()->values()->all());
    });

    it('shows a tab per settings group', function (): void {
        livewire(ManageSettings::class)
            ->assertOk()
            ->assertSee([__('settings/general.label'), __('settings/appearance.label'), __('settings/mail.label'), __('settings/socialite.label')]);
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
        $mail = resolve(MailSettings::class);

        expect($general->app_name)->not->toBeEmpty()
            ->and($general->default_locale)->toBe('en')
            ->and($general->timezone)->toBe('UTC')
            ->and($general->logo_path)->toBeNull()
            ->and($general->contact_email)->toBeNull()
            ->and(resolve(AppearanceSettings::class)->primaryColor())->toBe(ThemeColor::Indigo)
            // .env stays in charge of mail until someone says otherwise.
            ->and($mail->enabled)->toBeFalse()
            ->and($mail->port)->toBe(587)
            ->and(resolve(SocialiteSettings::class)->enabledProviders())->toBe([]);
    });

    it('keeps the contact address in General and drops the unreleased contact and social fields', function (): void {
        expect(DB::table('settings')->where('group', 'general')->where('name', 'contact_email')->exists())->toBeTrue()
            ->and(DB::table('settings')->whereIn('group', ['contact', 'social'])->exists())->toBeFalse();
    });
});

describe('the form', function (): void {
    it('fills every tab with the stored settings', function (): void {
        livewire(ManageSettings::class)
            ->assertSet('data.general.app_name', resolve(GeneralSettings::class)->app_name)
            ->assertSet('data.general.timezone', 'UTC')
            ->assertSet('data.appearance.primary_color', 'indigo')
            ->assertSet('data.mail.port', 587);
    });

    it('saves every tab at once', function (): void {
        livewire(ManageSettings::class)
            ->set('data.general.app_name', 'Acme Console')
            ->set('data.general.default_locale', 'ar')
            ->set('data.general.timezone', 'Africa/Cairo')
            ->set('data.general.contact_email', 'ops@example.com')
            ->set('data.appearance.primary_color', 'emerald')
            ->set('data.mail.from_name', 'Acme')
            ->set('data.socialite.github_client_id', 'github-client')
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified(__('admin.settings.saved'));

        $general = resolve(GeneralSettings::class)->refresh();

        expect($general->app_name)->toBe('Acme Console')
            ->and($general->default_locale)->toBe('ar')
            ->and($general->timezone)->toBe('Africa/Cairo')
            ->and($general->contact_email)->toBe('ops@example.com')
            ->and(resolve(AppearanceSettings::class)->refresh()->primaryColor())->toBe(ThemeColor::Emerald)
            ->and(resolve(MailSettings::class)->refresh()->from_name)->toBe('Acme')
            ->and(resolve(SocialiteSettings::class)->refresh()->github_client_id)->toBe('github-client');
    });

    it('validates every tab', function (array $values, string $field): void {
        $component = livewire(ManageSettings::class);

        foreach ($values as $path => $value) {
            $component->set("data.{$path}", $value);
        }

        $component->call('save')->assertHasErrors(["data.{$field}"]);
    })->with([
        'name is required' => [['general.app_name' => ''], 'general.app_name'],
        'language must be offered' => [['general.default_locale' => 'fr'], 'general.default_locale'],
        'time zone must exist' => [['general.timezone' => 'Mars/Olympus'], 'general.timezone'],
        'contact email must be an address' => [['general.contact_email' => 'nope'], 'general.contact_email'],
        'colour must be offered' => [['appearance.primary_color' => 'chartreuse'], 'appearance.primary_color'],
        'from address must be an address' => [['mail.from_address' => 'nope'], 'mail.from_address'],
        'port must be a port' => [['mail.port' => 70000], 'mail.port'],
        'a mail server is needed once switched on' => [['mail.enabled' => true, 'mail.host' => ''], 'mail.host'],
        'transport must be offered' => [['mail.transport' => 'carrier-pigeon'], 'mail.transport'],
        'a provider needs a client id once switched on' => [['socialite.google_enabled' => true, 'socialite.google_client_id' => ''], 'socialite.google_client_id'],
        'and a client secret, while none is stored' => [['socialite.google_enabled' => true, 'socialite.google_client_id' => 'id'], 'socialite.google_client_secret'],
    ]);
});

describe('secrets', function (): void {
    beforeEach(function (): void {
        $mail = resolve(MailSettings::class);
        $mail->password = 'smtp-Secret-1';
        $mail->save();

        $socialite = resolve(SocialiteSettings::class);
        $socialite->google_client_secret = 'google-Secret-1';
        $socialite->save();
    });

    it('never sends a stored secret to the browser', function (): void {
        $page = livewire(ManageSettings::class)
            ->assertSet('data.mail.password', null)
            ->assertSet('data.socialite.google_client_secret', null);

        expect($page->html())->not->toContain('smtp-Secret-1')->not->toContain('google-Secret-1');
    });

    it('keeps a secret when its field is left empty, and replaces it when one is typed', function (): void {
        livewire(ManageSettings::class)
            ->set('data.mail.from_name', 'Acme')
            ->call('save')
            ->assertHasNoFormErrors();

        expect(resolve(MailSettings::class)->refresh()->password)->toBe('smtp-Secret-1');

        livewire(ManageSettings::class)
            ->set('data.mail.password', 'smtp-Secret-2')
            ->call('save')
            ->assertHasNoFormErrors()
            // Emptied again after saving, not left in the page.
            ->assertSet('data.mail.password', null);

        expect(resolve(MailSettings::class)->refresh()->password)->toBe('smtp-Secret-2');
    });

    it('stores secrets encrypted and keeps them out of the activity log', function (): void {
        livewire(ManageSettings::class)
            ->set('data.mail.password', 'smtp-Secret-2')
            ->call('save');

        $stored = DB::table('settings')->where('group', 'mail')->where('name', 'password')->value('payload');
        $activity = Activity::query()->where('log_name', 'settings')->latest('id')->firstOrFail();

        expect($stored)->not->toContain('smtp-Secret')
            ->and(json_encode($activity->attribute_changes))->not->toContain('smtp-Secret');
    });
});

describe('email', function (): void {
    it('sends mail with these settings once switched on', function (): void {
        $mail = resolve(MailSettings::class);
        $mail->enabled = true;
        $mail->host = 'smtp.acme.test';
        $mail->port = 465;
        $mail->encryption = 'ssl';
        $mail->from_address = 'noreply@acme.test';
        $mail->save();

        app()->forgetInstance('mail.manager');
        resolve('mail.manager');

        expect(config('mail.default'))->toBe('smtp')
            ->and(config('mail.mailers.smtp.host'))->toBe('smtp.acme.test')
            ->and(config('mail.mailers.smtp.scheme'))->toBe('smtps')
            ->and(config('mail.from.address'))->toBe('noreply@acme.test');
    });

    it('leaves the .env mail configuration alone while switched off', function (): void {
        app()->forgetInstance('mail.manager');
        resolve('mail.manager');

        expect(config('mail.default'))->toBe('array');
    });

    it('sends a test email with unsaved values, then puts everything back', function (): void {
        resolve(MailService::class)->sendTest(['transport' => 'log', 'from_address' => 'noreply@acme.test'], 'omar@example.com');

        expect(config('mail.default'))->toBe('array')
            ->and(resolve(MailSettings::class)->refresh()->transport)->toBe('smtp');
    });

    it('reports why a test email could not be sent', function (): void {
        expect(fn () => resolve(MailService::class)->sendTest(['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1], 'omar@example.com'))
            ->toThrow(RuntimeException::class, 'Connection could not be established');

        expect(config('mail.default'))->toBe('array');
    });

    it('offers the test on the Email tab', function (): void {
        livewire(ManageSettings::class)->assertSee(__('settings/mail.test.action'));
    });
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
            ->set('data.mail.from_name', 'Acme Mail')
            ->call('save');

        $activities = Activity::query()->where('log_name', 'settings')->orderBy('id')->get();

        expect($activities)->toHaveCount(2)
            ->and($activities[0]->properties->get('group'))->toBe('general')
            ->and($activities[0]->newValues())->toBe(['app_name' => 'Acme Console'])
            ->and($activities[0]->causer_type)->toBe('admin')
            ->and($activities[1]->properties->get('group'))->toBe('mail')
            ->and($activities[1]->newValues())->toBe(['from_name' => 'Acme Mail']);

        livewire(ManageSettings::class)->call('save');

        expect(Activity::query()->where('log_name', 'settings')->count())->toBe(2);
    });

    it('labels the changed fields with the tab\'s own translations', function (): void {
        livewire(ManageSettings::class)
            ->set('data.mail.from_name', 'Acme Mail')
            ->call('save');

        $diff = ActivityPresenter::diff(Activity::query()->where('log_name', 'settings')->latest('id')->firstOrFail());

        expect($diff[0]['label'])->toBe(__('settings/mail.fields.from_name'));
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
