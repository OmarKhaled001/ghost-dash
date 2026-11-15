<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

it('wraps a success', function (): void {
    $user = User::factory()->create();

    $response = withToken(tokenFor($user))->getJson('/api/v1/auth/me')->assertOk();

    assertEnvelope($response, success: true);
    expect($response->json('data.user.email'))->toBe($user->email);
});

it('wraps a validation error as 422 with messages per field', function (): void {
    $response = postJson('/api/v1/auth/login', [])->assertStatus(422);

    assertEnvelope($response, success: false);
    expect($response->json('message'))->toBe(__('api.errors.validation'))
        ->and($response->json('errors'))->toHaveKeys(['email', 'password'])
        ->and($response->json('errors.email'))->toBeArray();
});

it('wraps a missing token as 401', function (): void {
    $response = getJson('/api/v1/auth/me')->assertStatus(401);

    assertEnvelope($response, success: false);
    expect($response->json('message'))->toBe(__('api.errors.unauthenticated'))
        ->and($response->json('errors'))->toBeNull();
});

it('wraps a token without the needed ability as 403', function (): void {
    $user = User::factory()->create();

    $response = withToken(tokenFor($user, ['account:write']))->getJson('/api/v1/auth/me')->assertStatus(403);

    assertEnvelope($response, success: false);
    expect($response->json('message'))->toBe(__('api.errors.forbidden'));
});

it('wraps an unknown route as 404', function (): void {
    $response = getJson('/api/v1/nothing-here')->assertStatus(404);

    assertEnvelope($response, success: false);
    expect($response->json('message'))->toBe(__('api.errors.not_found'));
});

it('wraps a wrong method as 405 and keeps the Allow header', function (): void {
    $response = getJson('/api/v1/auth/login')->assertStatus(405);

    assertEnvelope($response, success: false);
    expect($response->headers->get('Allow'))->toContain('POST');
});

it('wraps a rate limit as 429 with Retry-After', function (): void {
    foreach (range(1, 5) as $attempt) {
        postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'x']);
    }

    $response = postJson('/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'x'])->assertStatus(429);

    assertEnvelope($response, success: false);
    expect($response->json('message'))->toBe(__('api.errors.too_many_requests'))
        ->and((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0);
});

it('wraps an unexpected error as 500 without leaking what went wrong', function (): void {
    config(['app.debug' => false]);
    Route::middleware('api')->get('/api/v1/_boom', fn (): never => throw new RuntimeException('database password is hunter2'));

    $response = getJson('/api/v1/_boom')->assertStatus(500);

    assertEnvelope($response, success: false);
    expect($response->json('message'))->toBe(__('api.errors.server'))
        ->and($response->getContent())->not->toContain('hunter2')
        ->and($response->json())->not->toHaveKey('debug');
});

it('shows the real error only when APP_DEBUG is on', function (): void {
    config(['app.debug' => true]);
    Route::middleware('api')->get('/api/v1/_boom', fn (): never => throw new RuntimeException('the real reason'));

    $response = getJson('/api/v1/_boom')->assertStatus(500);

    assertEnvelope($response, success: false);
    expect($response->json('message'))->toBe('the real reason')
        ->and($response->json('debug.exception'))->toBe(RuntimeException::class);
});

it('answers in JSON even when the client did not ask for it', function (string $method, string $uri, int $status): void {
    // No Accept header: a browser, curl, or a careless client.
    $response = $this->call($method, $uri)->assertStatus($status);

    expect($response->headers->get('Content-Type'))->toContain('application/json');
    assertEnvelope($response, success: false);
})->with([
    'unauthenticated' => ['GET', '/api/v1/auth/me', 401],
    'validation' => ['POST', '/api/v1/auth/login', 422],
    'not found' => ['GET', '/api/v1/nothing-here', 404],
]);

it('leaves non-API pages alone', function (): void {
    get('/nothing-here')->assertNotFound()->assertHeader('Content-Type', 'text/html; charset=utf-8');
});

it('sends the security headers on API responses too', function (): void {
    getJson('/api/v1/auth/me')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});
