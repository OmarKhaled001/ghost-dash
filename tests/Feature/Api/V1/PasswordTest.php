<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Admin;
use App\Models\User;
use App\Notifications\Api\ResetPasswordNotification;
use App\Services\UserAuthService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

use function Illuminate\Support\defer;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

beforeEach(function (): void {
    $this->user = User::factory()->create(['email' => 'nour@example.com']);
});

describe('forgot password', function (): void {
    it('sends the mail after the response, so its cost does not show which addresses exist', function (): void {
        Notification::fake();

        resolve(UserAuthService::class)->sendPasswordResetLink('nour@example.com');

        Notification::assertNothingSent();

        defer()->invoke();

        Notification::assertSentTo($this->user, ResetPasswordNotification::class);
    });

    it('emails a reset link that points to the frontend', function (): void {
        Notification::fake();
        config(['ghostdash.frontend_url' => 'https://app.example.com']);

        $response = postJson('/api/v1/auth/forgot-password', ['email' => 'nour@example.com'])->assertOk();

        assertEnvelope($response, success: true);
        expect($response->json('message'))->toBe(__('api.messages.password_reset_link_sent'));

        Notification::assertSentTo($this->user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification): bool {
            $url = $notification->toMail($this->user)->actionUrl;

            return str_starts_with($url, 'https://app.example.com/reset-password?')
                && str_contains($url, 'token='.$notification->token)
                && str_contains($url, 'email=nour%40example.com');
        });
    });

    it('answers the same for an address without an account, and sends nothing', function (): void {
        Notification::fake();

        $known = postJson('/api/v1/auth/forgot-password', ['email' => 'nour@example.com'])->assertOk();
        $unknown = postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        expect($unknown->json())->toBe($known->json());

        Notification::assertSentTimes(ResetPasswordNotification::class, 1);
    });

    it('does not send a user reset link to an admin address', function (): void {
        Notification::fake();
        Admin::factory()->create(['email' => 'admin@example.com']);

        postJson('/api/v1/auth/forgot-password', ['email' => 'admin@example.com'])->assertOk();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseCount('admin_password_reset_tokens', 0);
    });

    it('validates the address', function (): void {
        postJson('/api/v1/auth/forgot-password', ['email' => 'nope'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', fn (string $message): bool => $message !== '');
    });

    it('throttles requests for one address', function (): void {
        Notification::fake();

        foreach (range(1, 3) as $attempt) {
            postJson('/api/v1/auth/forgot-password', ['email' => 'nour@example.com'])->assertOk();
        }

        postJson('/api/v1/auth/forgot-password', ['email' => 'nour@example.com'])->assertStatus(429);
    });
});

describe('reset password', function (): void {
    it('sets a new password, revokes every token and logs it', function (): void {
        $oldToken = tokenFor($this->user);
        $resetToken = Password::broker('users')->createToken($this->user);

        $response = postJson('/api/v1/auth/reset-password', [
            'email' => 'nour@example.com',
            'token' => $resetToken,
            'password' => 'A-brand-new-Passw0rd',
            'password_confirmation' => 'A-brand-new-Passw0rd',
        ])->assertOk();

        assertEnvelope($response, success: true);

        expect(Hash::check('A-brand-new-Passw0rd', $this->user->refresh()->password))->toBeTrue()
            ->and($this->user->tokens()->count())->toBe(0)
            ->and(Activity::query()->where('event', 'password_reset')->where('subject_id', $this->user->id)->exists())->toBeTrue();

        asNewRequest();
        withToken($oldToken)->getJson('/api/v1/auth/me')->assertStatus(401);

        // The reset token is single use.
        postJson('/api/v1/auth/reset-password', [
            'email' => 'nour@example.com',
            'token' => $resetToken,
            'password' => 'Yet-another-Passw0rd',
            'password_confirmation' => 'Yet-another-Passw0rd',
        ])->assertStatus(422);
    });

    it('answers a bad token and an unknown address identically', function (): void {
        $payload = ['token' => 'not-the-token', 'password' => 'A-brand-new-Passw0rd', 'password_confirmation' => 'A-brand-new-Passw0rd'];

        $badToken = postJson('/api/v1/auth/reset-password', ['email' => 'nour@example.com', ...$payload])->assertStatus(422);
        $unknown = postJson('/api/v1/auth/reset-password', ['email' => 'nobody@example.com', ...$payload])->assertStatus(422);

        assertEnvelope($badToken, success: false);

        expect($unknown->json())->toBe($badToken->json())
            ->and(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
    });

    it('cannot be used with a token issued for an admin', function (): void {
        $admin = Admin::factory()->create(['email' => 'nour@example.com']);
        $adminToken = Password::broker('admins')->createToken($admin);

        postJson('/api/v1/auth/reset-password', [
            'email' => 'nour@example.com',
            'token' => $adminToken,
            'password' => 'A-brand-new-Passw0rd',
            'password_confirmation' => 'A-brand-new-Passw0rd',
        ])->assertStatus(422);

        expect(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
    });

    it('validates the input', function (array $overrides, string $field): void {
        $response = postJson('/api/v1/auth/reset-password', [
            'email' => 'nour@example.com',
            'token' => 'x',
            'password' => 'A-brand-new-Passw0rd',
            'password_confirmation' => 'A-brand-new-Passw0rd',
            ...$overrides,
        ])->assertStatus(422);

        expect($response->json('errors'))->toHaveKey($field);
    })->with([
        'token is required' => [['token' => ''], 'token'],
        'email is required' => [['email' => ''], 'email'],
        'password follows the policy' => [['password' => 'weak', 'password_confirmation' => 'weak'], 'password'],
        'password must be confirmed' => [['password_confirmation' => 'Different-Passw0rd-1'], 'password'],
    ]);

    it('throttles attempts per IP', function (): void {
        foreach (range(1, 5) as $attempt) {
            postJson('/api/v1/auth/reset-password', ['email' => 'nour@example.com', 'token' => 'x', 'password' => 'A-brand-new-Passw0rd', 'password_confirmation' => 'A-brand-new-Passw0rd'])->assertStatus(422);
        }

        postJson('/api/v1/auth/reset-password', ['email' => 'nour@example.com', 'token' => 'x', 'password' => 'A-brand-new-Passw0rd', 'password_confirmation' => 'A-brand-new-Passw0rd'])->assertStatus(429);
    });
});

describe('change password', function (): void {
    it('changes the password and signs out the other devices', function (): void {
        $current = tokenFor($this->user);
        $other = tokenFor($this->user);

        $response = withToken($current)->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'A-brand-new-Passw0rd',
            'password_confirmation' => 'A-brand-new-Passw0rd',
        ])->assertOk();

        assertEnvelope($response, success: true);

        expect(Hash::check('A-brand-new-Passw0rd', $this->user->refresh()->password))->toBeTrue()
            ->and($this->user->tokens()->count())->toBe(1);

        asNewRequest();
        withToken($current)->getJson('/api/v1/auth/me')->assertOk();

        asNewRequest();
        withToken($other)->getJson('/api/v1/auth/me')->assertStatus(401);
    });

    it('requires the current password, so a stolen token is not enough', function (): void {
        $response = withToken(tokenFor($this->user))->putJson('/api/v1/auth/password', [
            'current_password' => 'not-my-password',
            'password' => 'A-brand-new-Passw0rd',
            'password_confirmation' => 'A-brand-new-Passw0rd',
        ])->assertStatus(422);

        expect($response->json('errors'))->toHaveKey('current_password')
            ->and(Hash::check('password', $this->user->refresh()->password))->toBeTrue();
    });

    it('validates the new password', function (array $overrides, string $field): void {
        $response = withToken(tokenFor($this->user))->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'A-brand-new-Passw0rd',
            'password_confirmation' => 'A-brand-new-Passw0rd',
            ...$overrides,
        ])->assertStatus(422);

        expect($response->json('errors'))->toHaveKey($field);
    })->with([
        'follows the policy' => [['password' => 'weak', 'password_confirmation' => 'weak'], 'password'],
        'must be confirmed' => [['password_confirmation' => 'Different-Passw0rd-1'], 'password'],
        'must differ from the current one' => [['password' => 'password', 'password_confirmation' => 'password'], 'password'],
    ]);

    it('requires a token', function (): void {
        $this->putJson('/api/v1/auth/password', [])->assertStatus(401);
    });

    it('logs that the password changed, not what it changed to', function (): void {
        withToken(tokenFor($this->user))->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'A-brand-new-Passw0rd',
            'password_confirmation' => 'A-brand-new-Passw0rd',
        ]);

        $activity = Activity::query()->where('event', 'updated')->where('subject_type', 'user')->latest('id')->firstOrFail();

        expect($activity->newValues())->toBe(['password' => '[redacted]'])
            ->and($activity->causer_type)->toBe('user')
            ->and($activity->causer_id)->toBe($this->user->id);
    });
});
