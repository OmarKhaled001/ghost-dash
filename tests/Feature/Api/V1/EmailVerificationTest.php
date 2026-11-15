<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\User;
use App\Notifications\Api\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\getJson;
use function Pest\Laravel\withToken;

function verificationUrl(User $user, ?string $hash = null, int $minutes = 60): string
{
    return URL::temporarySignedRoute('api.v1.auth.verify-email', now()->addMinutes($minutes), [
        'id' => $user->id,
        'hash' => $hash ?? sha1($user->email),
    ]);
}

beforeEach(function (): void {
    $this->user = User::factory()->unverified()->create();
});

it('verifies the address through the signed link, without a token', function (): void {
    $response = getJson(verificationUrl($this->user))->assertOk();

    assertEnvelope($response, success: true);

    expect($response->json('message'))->toBe(__('api.messages.email_verified'))
        ->and($this->user->refresh()->hasVerifiedEmail())->toBeTrue()
        ->and(Activity::query()->where('event', 'email_verified')->where('subject_id', $this->user->id)->exists())->toBeTrue();
});

it('works with the link exactly as it is emailed', function (): void {
    $url = (new VerifyEmailNotification)->toMail($this->user)->actionUrl;

    getJson($url)->assertOk();

    expect($this->user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('says so when the address is already verified', function (): void {
    $this->user->markEmailAsVerified();

    getJson(verificationUrl($this->user))
        ->assertOk()
        ->assertJsonPath('message', __('api.messages.email_already_verified'));
});

it('rejects a link that was tampered with', function (): void {
    $forged = preg_replace('/signature=[^&]+/', 'signature=forged', verificationUrl($this->user));

    $response = getJson($forged)->assertStatus(403);

    assertEnvelope($response, success: false);
    expect($response->json('message'))->toBe(__('api.errors.invalid_signature'))
        ->and($this->user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a link signed for another user', function (): void {
    $other = User::factory()->unverified()->create();

    // Take a valid signature for one user and point it at another id.
    $swapped = str_replace("/verify-email/{$this->user->id}/", "/verify-email/{$other->id}/", verificationUrl($this->user));

    getJson($swapped)->assertStatus(403);

    expect($other->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a correctly signed link whose hash is for a different address', function (): void {
    getJson(verificationUrl($this->user, sha1('old-address@example.com')))->assertStatus(403);

    expect($this->user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects an expired link', function (): void {
    $url = verificationUrl($this->user, minutes: 60);

    $this->travel(61)->minutes();

    getJson($url)->assertStatus(403);

    expect($this->user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email', function (): void {
    Notification::fake();

    $response = withToken(tokenFor($this->user))->postJson('/api/v1/auth/email/verification-notification')->assertOk();

    assertEnvelope($response, success: true);
    expect($response->json('message'))->toBe(__('api.messages.verification_link_sent'));

    Notification::assertSentTo($this->user, VerifyEmailNotification::class);
});

it('does not resend to an address that is verified already', function (): void {
    Notification::fake();
    $this->user->markEmailAsVerified();

    withToken(tokenFor($this->user))
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertOk()
        ->assertJsonPath('message', __('api.messages.email_already_verified'));

    Notification::assertNothingSent();
});

it('requires a token to resend', function (): void {
    $this->postJson('/api/v1/auth/email/verification-notification')->assertStatus(401);
});

it('throttles resending per user', function (): void {
    Notification::fake();
    $token = tokenFor($this->user);

    foreach (range(1, 3) as $attempt) {
        asNewRequest();
        withToken($token)->postJson('/api/v1/auth/email/verification-notification')->assertOk();
    }

    asNewRequest();
    withToken($token)->postJson('/api/v1/auth/email/verification-notification')->assertStatus(429);
});
