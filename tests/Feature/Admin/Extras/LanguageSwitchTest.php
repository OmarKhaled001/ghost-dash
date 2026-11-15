<?php

declare(strict_types=1);

use App\Settings\GeneralSettings;
use BezhanSalleh\LanguageSwitch\Http\Livewire\LanguageSwitchComponent;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;

use function Pest\Laravel\get;
use function Pest\Laravel\withCookie;
use function Pest\Laravel\withHeader;
use function Pest\Laravel\withSession;
use function Pest\Livewire\livewire;

it('offers Arabic and English', function (): void {
    expect(LanguageSwitch::make()->getLocales())->toEqualCanonicalizing(['en', 'ar']);
});

it('renders the panel left-to-right in English', function (): void {
    actingAsAdmin();

    get(panelUrl())->assertOk()->assertSeeHtml('lang="en"')->assertSeeHtml('dir="ltr"');
});

it('renders the panel right-to-left in Arabic', function (): void {
    actingAsAdmin();

    withSession(['locale' => 'ar']);

    get(panelUrl())->assertOk()->assertSeeHtml('lang="ar"')->assertSeeHtml('dir="rtl"')
        // Filament's own strings and the kit's are both translated.
        ->assertSee('لوحة التحكم');
});

it('translates the kit navigation into Arabic', function (): void {
    actingAsAdmin(superAdmin());

    withSession(['locale' => 'ar']);

    get(panelUrl())
        ->assertSee(__('admin.admins.plural', locale: 'ar'))
        ->assertSee(__('admin.navigation.access', locale: 'ar'));
});

it('shows the switcher on the login page', function (): void {
    get(panelUrl('login'))->assertOk()->assertSeeLivewire(LanguageSwitchComponent::class);
});

it('stores the chosen language in the session, a cookie and on the admin', function (): void {
    $admin = actingAsAdmin();
    $updatedAt = $admin->updated_at;

    livewire(LanguageSwitchComponent::class)->call('changeLocale', 'ar');

    expect(session('locale'))->toBe('ar')
        ->and($admin->refresh()->locale)->toBe('ar')
        // A display preference is not an edit of the account.
        ->and($admin->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('opens the panel in the language saved on the admin, in a new browser', function (): void {
    actingAsAdmin(admin(['locale' => 'ar']));

    // No session value and no cookie: only the stored preference.
    get(panelUrl())->assertSeeHtml('dir="rtl"');
});

it('remembers the language for a guest through the cookie', function (): void {
    withCookie('ghostdash_locale', 'ar');

    get(panelUrl('login'))->assertSeeHtml('dir="rtl"');
});

it('falls back to the default language from General settings', function (): void {
    $settings = resolve(GeneralSettings::class);
    $settings->default_locale = 'ar';
    $settings->save();

    get(panelUrl('login'))->assertSeeHtml('dir="rtl"');
});

it('does not let the browser language override the configured default', function (): void {
    withHeader('Accept-Language', 'ar-EG,ar;q=0.9');

    get(panelUrl('login'))->assertSeeHtml('dir="ltr"');
});

it('ignores a language that is not offered', function (): void {
    actingAsAdmin();

    withSession(['locale' => 'fr']);

    get(panelUrl())->assertSeeHtml('lang="en"');
});

it('has every admin translation key in both languages', function (): void {
    $keys = fn (array $lines, string $prefix = ''): array => collect($lines)
        ->flatMap(fn (mixed $value, string $key): array => is_array($value) ? $keys($value, $prefix.$key.'.') : [$prefix.$key])
        ->all();

    $keys = function (array $lines, string $prefix = '') use (&$keys): array {
        $found = [];

        foreach ($lines as $key => $value) {
            $found = is_array($value)
                ? [...$found, ...$keys($value, $prefix.$key.'.')]
                : [...$found, $prefix.$key];
        }

        return $found;
    };

    foreach (glob(lang_path('en/*.php')) as $file) {
        $name = basename($file);
        $arabic = lang_path('ar/'.$name);

        expect(file_exists($arabic))->toBeTrue("lang/ar/{$name} is missing");

        $missing = array_diff($keys(require $file), $keys(require $arabic));
        $extra = array_diff($keys(require $arabic), $keys(require $file));

        expect($missing)->toBe([], "lang/ar/{$name} is missing: ".implode(', ', $missing))
            ->and($extra)->toBe([], "lang/ar/{$name} has keys English lacks: ".implode(', ', $extra));
    }
});
