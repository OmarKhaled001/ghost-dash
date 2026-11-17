<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\Api\VerifyEmailNotification;
use App\Settings\SocialiteSettings;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Laravel\Socialite\Two\XProvider;

use function Pest\Laravel\postJson;

/**
 * Switch a provider on and make it answer the given access token with the
 * given person, as the real provider's API would.
 *
 * @param  class-string<AbstractProvider>  $driverClass
 * @param  array<string, mixed>  $raw
 */
function fakeProvider(string $provider, string $driverClass, ?array $person, array $raw = []): void
{
    $settings = resolve(SocialiteSettings::class);
    $settings->{"{$provider}_enabled"} = true;
    $settings->{"{$provider}_client_id"} = "{$provider}-id";
    $settings->{"{$provider}_client_secret"} = "{$provider}-secret";
    $settings->save();

    $driver = Mockery::mock($driverClass);

    if ($person === null) {
        $driver->shouldReceive('userFromToken')->andThrow(new RuntimeException('401 Unauthorized'));
    } else {
        $user = new SocialiteUser()->setRaw($raw)->map($person);
        $driver->shouldReceive('userFromToken')->with('provider-token')->andReturn($user);
    }

    Socialite::shouldReceive('forgetDrivers');
    Socialite::shouldReceive('driver')->andReturn($driver);
}

beforeEach(function (): void {
    Notification::fake();
});

it('signs up a new user, with the address verified when the provider vouches for it', function (): void {
    fakeProvider('google', GoogleProvider::class, ['id' => 'g-1', 'name' => 'Nour Ali', 'email' => 'Nour@Example.com'], ['email_verified' => true]);

    $response = postJson('/api/v1/auth/social/google', ['access_token' => 'provider-token', 'device_name' => 'iPhone'])
        ->assertCreated();

    assertEnvelope($response, success: true);

    $user = User::query()->where('email', 'nour@example.com')->firstOrFail();

    expect($response->json('data.token'))->toBeString()
        ->and($response->json('data.user.id'))->toBe($user->id)
        ->and($user->name)->toBe('Nour Ali')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->socialAccounts()->sole()->provider_user_id)->toBe('g-1')
        ->and($user->tokens()->sole()->name)->toBe('iPhone');

    Notification::assertNothingSentTo($user, VerifyEmailNotification::class);
});

it('signs the same person in again through the provider account', function (): void {
    fakeProvider('google', GoogleProvider::class, ['id' => 'g-1', 'name' => 'Nour Ali', 'email' => 'nour@example.com'], ['email_verified' => true]);

    postJson('/api/v1/auth/social/google', ['access_token' => 'provider-token'])->assertCreated();
    postJson('/api/v1/auth/social/google', ['access_token' => 'provider-token'])->assertOk();

    expect(User::query()->count())->toBe(1)
        ->and(SocialAccount::query()->count())->toBe(1);
});

it('joins an existing account when the provider vouches for the address', function (): void {
    $user = User::factory()->create(['email' => 'nour@example.com']);
    fakeProvider('github', GithubProvider::class, ['id' => '42', 'name' => 'Nour', 'email' => 'nour@example.com']);

    $response = postJson('/api/v1/auth/social/github', ['access_token' => 'provider-token'])->assertOk();

    expect($response->json('data.user.id'))->toBe($user->id)
        ->and($user->socialAccounts()->sole()->provider->value)->toBe('github');
});

it('refuses to join an existing account on an address the provider has not checked', function (string $provider, string $driver, array $raw): void {
    User::factory()->create(['email' => 'nour@example.com']);
    fakeProvider($provider, $driver, ['id' => 'someone', 'name' => 'Mallory', 'email' => 'nour@example.com'], $raw);

    postJson("/api/v1/auth/social/{$provider}", ['access_token' => 'provider-token'])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', __('api.social.email_taken'));

    expect(SocialAccount::query()->count())->toBe(0);
})->with([
    'google, unverified' => ['google', GoogleProvider::class, ['email_verified' => false]],
    'x, which never says' => ['x', XProvider::class, []],
]);

it('signs up with an unverified address when the provider does not vouch for it, and sends the check', function (): void {
    fakeProvider('x', XProvider::class, ['id' => 'x-1', 'nickname' => 'nour', 'email' => 'nour@example.com']);

    postJson('/api/v1/auth/social/x', ['access_token' => 'provider-token'])->assertCreated();

    $user = User::query()->where('email', 'nour@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->name)->toBe('nour');

    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('rejects a token the provider does not accept', function (): void {
    fakeProvider('google', GoogleProvider::class, null);

    postJson('/api/v1/auth/social/google', ['access_token' => 'provider-token'])
        ->assertStatus(422)
        ->assertJsonPath('errors.access_token.0', __('api.social.invalid_token'));
});

it('needs an email address from the provider', function (): void {
    fakeProvider('github', GithubProvider::class, ['id' => '42', 'name' => 'Nour', 'email' => null]);

    postJson('/api/v1/auth/social/github', ['access_token' => 'provider-token'])
        ->assertStatus(422)
        ->assertJsonPath('errors.access_token.0', __('api.social.no_email'));

    expect(User::query()->count())->toBe(0);
});

it('answers 404 for a provider that is switched off or unknown', function (string $provider): void {
    postJson("/api/v1/auth/social/{$provider}", ['access_token' => 'provider-token'])->assertNotFound();
})->with(['google', 'myspace']);

it('validates the request', function (): void {
    fakeProvider('google', GoogleProvider::class, null);

    postJson('/api/v1/auth/social/google', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['access_token']);
});

it('is rate limited per address', function (): void {
    foreach (range(1, 10) as $attempt) {
        postJson('/api/v1/auth/social/google', ['access_token' => 'x'])->assertNotFound();
    }

    postJson('/api/v1/auth/social/google', ['access_token' => 'x'])->assertStatus(429);
});
