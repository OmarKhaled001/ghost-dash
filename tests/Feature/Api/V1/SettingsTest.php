<?php

declare(strict_types=1);

use App\Settings\GeneralSettings;
use App\Settings\SocialiteSettings;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\getJson;

it('returns what the settings make public, without signing in', function (): void {
    $general = resolve(GeneralSettings::class);
    $general->app_name = 'Acme';
    $general->logo_path = 'branding/logo.png';
    $general->contact_email = 'hello@acme.test';
    $general->save();

    $response = getJson('/api/v1/settings')->assertOk();

    assertEnvelope($response, success: true);

    // Groups a project adds appear here too.
    expect(array_keys($response->json('data')))->toContain('appearance', 'general', 'socialite')
        ->and($response->json('data.general'))->toBe([
            'app_name' => 'Acme',
            'logo_url' => Storage::disk('public')->url('branding/logo.png'),
            'favicon_url' => null,
            'default_locale' => 'en',
            'contact_email' => 'hello@acme.test',
        ])
        ->and($response->json('data.appearance.primary_color'))->toBe('indigo')
        ->and($response->json('data.socialite'))->toBe(['providers' => []]);
});

it('lists the social sign-in providers that are ready, and never their credentials', function (): void {
    $socialite = resolve(SocialiteSettings::class);
    $socialite->google_enabled = true;
    $socialite->google_client_id = 'google-id';
    $socialite->google_client_secret = 'google-Secret-1';
    // Switched on but without a secret: not usable, so not listed.
    $socialite->github_enabled = true;
    $socialite->github_client_id = 'github-id';
    $socialite->save();

    $response = getJson('/api/v1/settings')->assertOk();

    expect($response->json('data.socialite'))->toBe(['providers' => ['google']])
        ->and($response->getContent())->not->toContain('google-Secret-1')->not->toContain('google-id');
});

it('exposes no stored file paths, mail settings or anything a settings class did not choose', function (): void {
    $json = getJson('/api/v1/settings')->assertOk()->json('data');

    expect($json)->not->toHaveKey('mail')
        ->and($json['general'])->not->toHaveKeys(['logo_path', 'favicon_path', 'timezone']);
});

it('is rate limited per address', function (): void {
    foreach (range(1, 120) as $attempt) {
        getJson('/api/v1/settings')->assertOk();
    }

    getJson('/api/v1/settings')->assertStatus(429);
});
