<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\User;
use App\Notifications\Api\VerifyEmailNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

function registration(array $overrides = []): array
{
    return [
        'name' => 'Nour Saleh',
        'email' => 'nour@example.com',
        'password' => 'A-very-strong-Passw0rd',
        'password_confirmation' => 'A-very-strong-Passw0rd',
        ...$overrides,
    ];
}

it('creates an account and returns a working token', function (): void {
    Notification::fake();

    $response = postJson('/api/v1/auth/register', registration(['device_name' => 'iPhone']))->assertCreated();

    assertEnvelope($response, success: true);

    $user = User::query()->where('email', 'nour@example.com')->firstOrFail();

    expect($response->json('data.user'))->toBe([
        'id' => $user->id,
        'name' => 'Nour Saleh',
        'email' => 'nour@example.com',
        'email_verified_at' => null,
        'created_at' => $user->created_at->toIso8601String(),
    ])
        ->and($response->json('data.token_type'))->toBe('Bearer')
        ->and($response->json('data.abilities'))->toBe(['account:read', 'account:write'])
        ->and($response->json('data.expires_at'))->not->toBeNull()
        ->and(Hash::check('A-very-strong-Passw0rd', $user->password))->toBeTrue()
        ->and($user->tokens()->first()->name)->toBe('iPhone');

    asNewRequest();
    withToken($response->json('data.token'))->getJson('/api/v1/auth/me')->assertOk();
});

it('never returns the password or its hash', function (): void {
    Notification::fake();

    $content = postJson('/api/v1/auth/register', registration())->getContent();

    expect($content)->not->toContain('A-very-strong-Passw0rd')
        ->not->toContain('password')
        ->not->toContain('remember_token');
});

it('sends a verification email whose link points at the API', function (): void {
    Notification::fake();

    postJson('/api/v1/auth/register', registration());

    $user = User::query()->where('email', 'nour@example.com')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_contains($url, "/api/v1/auth/verify-email/{$user->id}/") && str_contains($url, 'signature=');
    });
});

it('logs the registration', function (): void {
    Notification::fake();

    postJson('/api/v1/auth/register', registration());

    $user = User::query()->where('email', 'nour@example.com')->firstOrFail();

    expect(Activity::query()->where('event', 'registered')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('validates the input', function (array $overrides, string $field): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = postJson('/api/v1/auth/register', registration($overrides))->assertStatus(422);

    assertEnvelope($response, success: false);
    expect($response->json('errors'))->toHaveKey($field);
    expect(User::query()->where('email', 'nour@example.com')->exists())->toBeFalse();
})->with([
    'name is required' => [['name' => ''], 'name'],
    'email is required' => [['email' => ''], 'email'],
    'email must be an address' => [['email' => 'not-an-email'], 'email'],
    'email must be lowercase' => [['email' => 'Nour@Example.com'], 'email'],
    'email must be unique' => [['email' => 'taken@example.com'], 'email'],
    'password is required' => [['password' => '', 'password_confirmation' => ''], 'password'],
    'password must be confirmed' => [['password_confirmation' => 'Something-else-123'], 'password'],
    'password must be long enough' => [['password' => 'Short1a', 'password_confirmation' => 'Short1a'], 'password'],
    'password needs mixed case' => [['password' => 'all-lowercase-12345', 'password_confirmation' => 'all-lowercase-12345'], 'password'],
    'password needs a number' => [['password' => 'No-Numbers-In-Here', 'password_confirmation' => 'No-Numbers-In-Here'], 'password'],
]);

it('still creates the account when the verification email cannot be sent', function (): void {
    Notification::shouldReceive('send')->andThrow(new RuntimeException('mailer down'));

    postJson('/api/v1/auth/register', registration())->assertCreated();

    expect(User::query()->where('email', 'nour@example.com')->exists())->toBeTrue();
});

it('throttles registration per IP', function (): void {
    Notification::fake();

    foreach (range(1, 5) as $attempt) {
        postJson('/api/v1/auth/register', registration(['email' => "user{$attempt}@example.com"]))->assertCreated();
    }

    postJson('/api/v1/auth/register', registration(['email' => 'user6@example.com']))->assertStatus(429);

    expect(User::query()->count())->toBe(5);
});
