<?php

declare(strict_types=1);

use App\Settings\ContactSettings;
use App\Settings\GeneralSettings;
use App\Settings\SocialSettings;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\getJson;

it('returns what the settings make public, without signing in', function (): void {
    $general = resolve(GeneralSettings::class);
    $general->app_name = 'Acme';
    $general->logo_path = 'branding/logo.png';
    $general->save();

    $contact = resolve(ContactSettings::class);
    $contact->email = 'hello@acme.test';
    $contact->whatsapp = '201001234567';
    $contact->save();

    $social = resolve(SocialSettings::class);
    $social->instagram = 'https://instagram.com/acme';
    $social->save();

    $response = getJson('/api/v1/settings')->assertOk();

    assertEnvelope($response, success: true);

    // Tabs a project adds (seo, for one) appear here too.
    expect(array_keys($response->json('data')))->toContain('appearance', 'contact', 'general', 'social')
        ->and($response->json('data.general'))->toBe([
            'app_name' => 'Acme',
            'logo_url' => Storage::disk('public')->url('branding/logo.png'),
            'favicon_url' => null,
            'default_locale' => 'en',
        ])
        ->and($response->json('data.contact.email'))->toBe('hello@acme.test')
        ->and($response->json('data.contact.whatsapp_url'))->toBe('https://wa.me/201001234567')
        // Only the networks that are filled in.
        ->and($response->json('data.social'))->toBe(['instagram' => 'https://instagram.com/acme'])
        ->and($response->json('data.appearance.primary_color'))->toBe('indigo');
});

it('exposes no stored file paths or anything a settings class did not choose', function (): void {
    $json = getJson('/api/v1/settings')->assertOk()->json('data');

    expect($json['general'])->not->toHaveKeys(['logo_path', 'favicon_path', 'timezone']);
});

it('is rate limited per address', function (): void {
    foreach (range(1, 120) as $attempt) {
        getJson('/api/v1/settings')->assertOk();
    }

    getJson('/api/v1/settings')->assertStatus(429);
});
