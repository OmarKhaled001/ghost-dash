<?php

declare(strict_types=1);

use App\Filament\Pages\ManageGeneralSettings;
use App\Models\Activity;
use App\Settings\GeneralSettings;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    actingAsAdmin(adminWith(['manage_settings']));
});

it('ships sensible defaults', function (): void {
    $settings = resolve(GeneralSettings::class);

    expect($settings->app_name)->not->toBeEmpty()
        ->and($settings->default_locale)->toBe('en')
        ->and($settings->timezone)->toBe('UTC')
        ->and($settings->logo_path)->toBeNull();
});

it('fills the form with the stored settings', function (): void {
    livewire(ManageGeneralSettings::class)
        ->assertFormSet([
            'app_name' => resolve(GeneralSettings::class)->app_name,
            'default_locale' => 'en',
            'timezone' => 'UTC',
        ]);
});

it('saves the settings', function (): void {
    livewire(ManageGeneralSettings::class)
        ->fillForm([
            'app_name' => 'Acme Console',
            'default_locale' => 'ar',
            'timezone' => 'Africa/Cairo',
            'contact_email' => 'ops@example.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = resolve(GeneralSettings::class)->refresh();

    expect($settings->app_name)->toBe('Acme Console')
        ->and($settings->default_locale)->toBe('ar')
        ->and($settings->timezone)->toBe('Africa/Cairo')
        ->and($settings->contact_email)->toBe('ops@example.com');
});

it('validates the form', function (array $input, array $errors): void {
    livewire(ManageGeneralSettings::class)
        ->fillForm($input)
        ->call('save')
        ->assertHasFormErrors($errors);
})->with([
    'name is required' => [['app_name' => ''], ['app_name' => 'required']],
    'language is required' => [['default_locale' => null], ['default_locale' => 'required']],
    'language must be offered' => [['default_locale' => 'fr'], ['default_locale']],
    'time zone must exist' => [['timezone' => 'Mars/Olympus'], ['timezone']],
    'contact must be an address' => [['contact_email' => 'nope'], ['contact_email' => 'email']],
]);

it('stores an uploaded logo on the public disk', function (): void {
    Storage::fake('public');

    livewire(ManageGeneralSettings::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->image('logo.png', 200, 60)])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = resolve(GeneralSettings::class)->refresh()->logo_path;

    expect($path)->toStartWith('branding/');
    Storage::disk('public')->assertExists($path);
});

it('rejects an SVG logo, which could carry script', function (): void {
    Storage::fake('public');

    livewire(ManageGeneralSettings::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
        ->call('save')
        ->assertHasFormErrors(['logo_path']);
});

it('keeps the stored logo on a later save, and refuses a path the browser made up', function (): void {
    Storage::fake('public');

    livewire(ManageGeneralSettings::class)
        ->fillForm(['logo_path' => UploadedFile::fake()->image('logo.png', 200, 60)])
        ->call('save')
        ->assertHasNoFormErrors();

    $logo = resolve(GeneralSettings::class)->refresh()->logo_path;

    livewire(ManageGeneralSettings::class)
        ->fillForm(['app_name' => 'Still Has Its Logo'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(resolve(GeneralSettings::class)->refresh()->logo_path)->toBe($logo);

    livewire(ManageGeneralSettings::class)
        ->set('data.logo_path', ['forged' => 'avatars/somebody-else.png'])
        ->call('save')
        ->assertHasFormErrors(['logo_path']);

    expect(resolve(GeneralSettings::class)->refresh()->logo_path)->toBe($logo);
});

it('logs what changed, and nothing when nothing did', function (): void {
    livewire(ManageGeneralSettings::class)
        ->fillForm(['app_name' => 'Acme Console'])
        ->call('save');

    $activity = Activity::query()->where('log_name', 'settings')->latest('id')->first();

    expect($activity->newValues())->toBe(['app_name' => 'Acme Console'])
        ->and(array_keys($activity->oldValues()))->toBe(['app_name'])
        ->and($activity->causer_type)->toBe('admin');

    $count = Activity::query()->where('log_name', 'settings')->count();

    livewire(ManageGeneralSettings::class)->call('save');

    expect(Activity::query()->where('log_name', 'settings')->count())->toBe($count);
});

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
