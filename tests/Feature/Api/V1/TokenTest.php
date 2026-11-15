<?php

declare(strict_types=1);

use App\Enums\TokenAbility;
use App\Models\Activity;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\withToken;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

describe('me', function (): void {
    it('returns the authenticated user', function (): void {
        $response = withToken(tokenFor($this->user))->getJson('/api/v1/auth/me')->assertOk();

        assertEnvelope($response, success: true);

        expect($response->json('data.user'))->toBe([
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'email_verified_at' => $this->user->email_verified_at->toIso8601String(),
            'created_at' => $this->user->created_at->toIso8601String(),
        ]);
    });

    it('never returns another user', function (): void {
        $other = User::factory()->create();

        $response = withToken(tokenFor($this->user))->getJson('/api/v1/auth/me');

        expect($response->json('data.user.id'))->toBe($this->user->id)
            ->and($response->getContent())->not->toContain($other->email);
    });

    it('rejects a missing, malformed or unknown token', function (?string $token): void {
        $request = $token === null ? $this : withToken($token);

        assertEnvelope($request->getJson('/api/v1/auth/me')->assertStatus(401), success: false);
    })->with([
        'no token' => [null],
        'garbage' => ['not-a-token'],
        'well formed but unknown' => ['999|'.str_repeat('a', 40)],
    ]);
});

describe('expiry', function (): void {
    it('rejects a token past its own expires_at', function (): void {
        $token = $this->user->createToken('short', ['*'], now()->addMinutes(10))->plainTextToken;

        withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->travel(11)->minutes();
        asNewRequest();

        assertEnvelope(withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401), success: false);
    });

    it('rejects a token older than SANCTUM_TOKEN_EXPIRATION even without expires_at', function (): void {
        config(['sanctum.expiration' => 60]);
        $token = $this->user->createToken('legacy')->plainTextToken;

        $this->travel(59)->minutes();
        withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->travel(2)->minutes();
        asNewRequest();

        withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    });

    it('issues tokens that last exactly SANCTUM_TOKEN_EXPIRATION minutes', function (): void {
        config(['sanctum.expiration' => 15]);

        $token = $this->postJson('/api/v1/auth/login', ['email' => $this->user->email, 'password' => 'password'])->json('data.token');

        $this->travel(14)->minutes();
        asNewRequest();
        withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->travel(2)->minutes();
        asNewRequest();
        withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    });

    it('schedules pruning of expired tokens', function (): void {
        $event = collect(resolve(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'sanctum:prune-expired'));

        expect($event)->not->toBeNull()->and($event->command)->toContain('--hours=24');
    });
});

describe('revocation', function (): void {
    it('revokes only the current token on logout', function (): void {
        $phone = tokenFor($this->user);
        $laptop = tokenFor($this->user);

        $response = withToken($phone)->postJson('/api/v1/auth/logout')->assertOk();

        assertEnvelope($response, success: true);
        expect($this->user->tokens()->count())->toBe(1);

        asNewRequest();
        withToken($phone)->getJson('/api/v1/auth/me')->assertStatus(401);

        asNewRequest();
        withToken($laptop)->getJson('/api/v1/auth/me')->assertOk();
    });

    it('revokes every token on logout-all', function (): void {
        $phone = tokenFor($this->user);
        $laptop = tokenFor($this->user);
        $someoneElse = tokenFor(User::factory()->create());

        withToken($phone)->postJson('/api/v1/auth/logout-all')->assertOk();

        expect($this->user->tokens()->count())->toBe(0)
            // Other people's tokens are untouched.
            ->and(PersonalAccessToken::query()->count())->toBe(1);

        asNewRequest();
        withToken($laptop)->getJson('/api/v1/auth/me')->assertStatus(401);

        asNewRequest();
        withToken($someoneElse)->getJson('/api/v1/auth/me')->assertOk();
    });

    it('lets a token without any ability revoke itself', function (): void {
        $token = tokenFor($this->user, ['nothing:useful']);

        withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        expect($this->user->tokens()->count())->toBe(0);
    });

    it('does not let a read-only token revoke the others', function (): void {
        $readOnly = tokenFor($this->user, [TokenAbility::AccountRead->value]);
        tokenFor($this->user);

        assertEnvelope(withToken($readOnly)->postJson('/api/v1/auth/logout-all')->assertForbidden(), success: false);

        expect($this->user->tokens()->count())->toBe(2);
    });

    it('requires a token to log out', function (): void {
        $this->postJson('/api/v1/auth/logout')->assertStatus(401);
        $this->postJson('/api/v1/auth/logout-all')->assertStatus(401);
    });

    it('logs the sign-out', function (): void {
        withToken(tokenFor($this->user))->postJson('/api/v1/auth/logout');

        $activity = Activity::query()->where('event', 'logout')->latest('id')->firstOrFail();

        expect($activity->causer_id)->toBe($this->user->id)
            ->and($activity->properties->get('guard'))->toBe('sanctum');
    });

    it('stops working when an admin sets a new password, and only then', function (): void {
        $token = tokenFor($this->user);

        resolve(UserService::class)->update($this->user, ['name' => 'Renamed', 'password' => null]);
        asNewRequest();
        withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        resolve(UserService::class)->update($this->user, ['password' => 'A-brand-new-Passw0rd']);
        asNewRequest();
        withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    });

    it('stops working when the account is deleted', function (): void {
        $token = tokenFor($this->user);

        resolve(UserService::class)->delete($this->user);
        asNewRequest();

        withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    });
});

describe('abilities', function (): void {
    it('requires account:read for me', function (): void {
        withToken(tokenFor($this->user, ['account:write']))->getJson('/api/v1/auth/me')->assertStatus(403);
    });

    it('requires account:write to change the password or resend verification', function (string $method, string $uri): void {
        $response = withToken(tokenFor($this->user, ['account:read']))->json($method, $uri)->assertStatus(403);

        assertEnvelope($response, success: false);
    })->with([
        ['PUT', '/api/v1/auth/password'],
        ['POST', '/api/v1/auth/email/verification-notification'],
    ]);
});

it('throttles me per user rather than per IP', function (): void {
    $mine = tokenFor($this->user);
    $theirs = tokenFor(User::factory()->create());

    foreach (range(1, 60) as $attempt) {
        asNewRequest();
        withToken($mine)->getJson('/api/v1/auth/me')->assertOk();
    }

    asNewRequest();
    withToken($mine)->getJson('/api/v1/auth/me')->assertStatus(429);

    asNewRequest();
    withToken($theirs)->getJson('/api/v1/auth/me')->assertOk();
});
