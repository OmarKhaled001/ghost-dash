<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

it('renders the password reset request page', function (): void {
    get(panelUrl('password-reset/request'))->assertOk();
});

it('emails an admin a reset link that points into the panel', function (): void {
    Notification::fake();

    $admin = admin();

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => $admin->email])
        ->call('request')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($admin, ResetPasswordNotification::class, fn (ResetPasswordNotification $notification): bool => str_contains($notification->url, panelUrl('password-reset/reset')));

    $this->assertDatabaseHas('admin_password_reset_tokens', ['email' => $admin->email]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $admin->email]);
});

it('does not send an admin reset link for an end user address', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request');

    Notification::assertNothingSent();
});

it('does not send a reset link to an inactive admin', function (): void {
    Notification::fake();

    $admin = Admin::factory()->inactive()->create();

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => $admin->email])
        ->call('request');

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function (): void {
    $admin = admin();
    $token = Password::broker('admins')->createToken($admin);

    livewire(ResetPassword::class, ['email' => $admin->email, 'token' => $token])
        ->fillForm([
            'password' => 'A-brand-new-Passw0rd',
            'passwordConfirmation' => 'A-brand-new-Passw0rd',
        ])
        ->call('resetPassword')
        ->assertHasNoFormErrors();

    expect(Hash::check('A-brand-new-Passw0rd', $admin->refresh()->password))->toBeTrue();
});

it('enforces the password policy when resetting', function (): void {
    $admin = admin();
    $token = Password::broker('admins')->createToken($admin);

    livewire(ResetPassword::class, ['email' => $admin->email, 'token' => $token])
        ->fillForm([
            'password' => 'short',
            'passwordConfirmation' => 'short',
        ])
        ->call('resetPassword')
        ->assertHasFormErrors(['password']);

    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue();
});

it('sends the panel reset link when a reset is started outside the panel', function (): void {
    Notification::fake();

    $admin = admin();

    Password::broker('admins')->sendResetLink(['email' => $admin->email]);

    Notification::assertSentTo($admin, ResetPasswordNotification::class, fn (ResetPasswordNotification $notification): bool => str_contains($notification->url, panelUrl('password-reset/reset')));
});
