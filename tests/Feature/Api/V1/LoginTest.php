<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

beforeEach(function (): void {
    $this->user = User::factory()->create(['email' => 'nour@example.com']);
});

it('exchanges valid credentials for a token', function (): void {
    $response = postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'password'])->assertOk();

    assertEnvelope($response, success: true);

    expect($response->json('message'))->toBe(__('api.messages.logged_in'))
        ->and($response->json('data.user.id'))->toBe($this->user->id)
        ->and($response->json('data.token'))->toBeString()
        ->and($response->json('data.token_type'))->toBe('Bearer')
        ->and($response->json('data.abilities'))->toBe(['account:read', 'account:write']);

    asNewRequest();
    withToken($response->json('data.token'))->getJson('/api/v1/auth/me')->assertOk();
});

it('spends the same hashing work on an unknown address as on a known one', function (): void {
    // Counts what the real hasher is asked to do.
    $hasher = new class(resolve(Hasher::class)) implements Hasher
    {
        public int $made = 0;

        public int $checked = 0;

        public function __construct(private readonly Hasher $inner) {}

        public function info($hashedValue): array
        {
            return $this->inner->info($hashedValue);
        }

        public function make(#[SensitiveParameter] $value, array $options = []): string
        {
            $this->made++;

            return $this->inner->make($value, $options);
        }

        public function check(#[SensitiveParameter] $value, $hashedValue, array $options = []): bool
        {
            $this->checked++;

            return $this->inner->check($value, $hashedValue, $options);
        }

        public function needsRehash($hashedValue, array $options = []): bool
        {
            return $this->inner->needsRehash($hashedValue, $options);
        }
    };

    app()->instance(Hasher::class, $hasher);

    // The decoy that stands in for a missing account is made once...
    postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'whatever'])->assertStatus(422);

    expect($hasher->made)->toBe(1)->and($hasher->checked)->toBe(1);

    // ...and from then on an unknown address costs one check, like a known one.
    postJson('/api/v1/auth/login', ['email' => 'nobody-else@example.com', 'password' => 'whatever'])->assertStatus(422);
    postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'not-the-password'])->assertStatus(422);

    expect($hasher->made)->toBe(1)->and($hasher->checked)->toBe(3);
});

it('stores only a hash of the token', function (): void {
    $plain = postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'password'])->json('data.token');

    $stored = PersonalAccessToken::query()->firstOrFail();

    expect($stored->token)->not->toBe($plain)
        ->and($plain)->not->toContain($stored->token);
});

it('stamps the token with an expiry from SANCTUM_TOKEN_EXPIRATION', function (): void {
    config(['sanctum.expiration' => 30]);
    $this->freezeSecond();

    $response = postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'password']);

    expect($response->json('data.expires_at'))->toBe(now()->addMinutes(30)->toIso8601String())
        ->and(PersonalAccessToken::query()->firstOrFail()->expires_at->equalTo(now()->addMinutes(30)))->toBeTrue();
});

it('names the token after the device', function (): void {
    postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'password', 'device_name' => 'Pixel 9']);

    expect(PersonalAccessToken::query()->firstOrFail()->name)->toBe('Pixel 9');
});

it('issues a narrower token when asked', function (): void {
    $response = postJson('/api/v1/auth/login', [
        'email' => 'nour@example.com',
        'password' => 'password',
        'abilities' => ['account:read'],
    ])->assertOk();

    expect($response->json('data.abilities'))->toBe(['account:read']);

    asNewRequest();
    withToken($response->json('data.token'))
        ->putJson('/api/v1/auth/password', [])
        ->assertStatus(403);
});

it('refuses an ability that does not exist', function (): void {
    $response = postJson('/api/v1/auth/login', [
        'email' => 'nour@example.com',
        'password' => 'password',
        'abilities' => ['*'],
    ])->assertStatus(422);

    expect($response->json('errors'))->toHaveKey('abilities.0');
});

it('answers a wrong password and an unknown address identically', function (): void {
    $wrongPassword = postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'nope'])->assertStatus(422);
    $unknownEmail = postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'nope'])->assertStatus(422);

    assertEnvelope($wrongPassword, success: false);

    expect($wrongPassword->json('errors.email'))->toBe([__('auth.failed')])
        ->and($unknownEmail->json())->toBe($wrongPassword->json());
});

it('does not let an admin sign in through the user API', function (): void {
    $admin = Admin::factory()->create(['email' => 'admin@example.com']);

    postJson('/api/v1/auth/login', ['email' => $admin->email, 'password' => 'password'])->assertStatus(422);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('validates the input', function (array $payload, string $field): void {
    $response = postJson('/api/v1/auth/login', $payload)->assertStatus(422);

    expect($response->json('errors'))->toHaveKey($field);
})->with([
    'email is required' => [['password' => 'x'], 'email'],
    'email must be an address' => [['email' => 'nope', 'password' => 'x'], 'email'],
    'password is required' => [['email' => 'nour@example.com'], 'password'],
    'abilities must be a list' => [['email' => 'nour@example.com', 'password' => 'x', 'abilities' => 'account:read'], 'abilities'],
]);

it('logs successful and failed sign-ins with the sanctum guard', function (): void {
    postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'the-wrong-one']);
    postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'password']);

    $failed = Activity::query()->where('event', 'failed_login')->latest('id')->firstOrFail();
    $login = Activity::query()->where('event', 'login')->latest('id')->firstOrFail();

    expect($failed->properties->get('guard'))->toBe('sanctum')
        ->and($failed->properties->get('email'))->toBe('nour@example.com')
        ->and((string) $failed->getRawOriginal('properties'))->not->toContain('the-wrong-one')
        ->and($login->properties->get('guard'))->toBe('sanctum')
        ->and($login->causer_type)->toBe('user')
        ->and($login->causer_id)->toBe($this->user->id);
});

it('locks out after five attempts for one address, and logs it', function (): void {
    foreach (range(1, 5) as $attempt) {
        postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'wrong'])->assertStatus(422);
    }

    // Even the right password is refused while locked out.
    $response = postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'password'])->assertStatus(429);

    assertEnvelope($response, success: false);

    $lockout = Activity::query()->where('event', 'lockout')->latest('id')->firstOrFail();

    expect($lockout->properties->get('guard'))->toBe('sanctum')
        ->and($lockout->properties->get('email'))->toBe('nour@example.com');
});

it('counts attempts per address, so one locked address does not block another', function (): void {
    User::factory()->create(['email' => 'other@example.com']);

    foreach (range(1, 6) as $attempt) {
        postJson('/api/v1/auth/login', ['email' => 'nour@example.com', 'password' => 'wrong']);
    }

    postJson('/api/v1/auth/login', ['email' => 'other@example.com', 'password' => 'password'])->assertOk();
});
