<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\MultiFactor\Email\Notifications\VerifyEmailAuthentication;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Livewire\livewire;

it('offers app and email authentication on the admin panel', function (): void {
    expect(array_keys(Filament::getPanel('admin')->getMultiFactorAuthenticationProviders()))
        ->toBe(['app', 'email_code']);
});

it('challenges an admin who uses an authenticator app before signing them in', function (): void {
    $provider = AppAuthentication::make();
    $admin = admin();
    $admin->saveAppAuthenticationSecret($provider->generateSecret());

    $component = livewire(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertNoRedirect();

    // A correct password alone is not a session.
    assertGuest('admin');

    $component
        ->set('data.multiFactor.app.code', $provider->getCurrentCode($admin))
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(Filament::getUrl());

    assertAuthenticatedAs($admin, 'admin');
});

it('rejects a wrong authenticator code', function (): void {
    $provider = AppAuthentication::make();
    $admin = admin();
    $admin->saveAppAuthenticationSecret($provider->generateSecret());

    $validCode = $provider->getCurrentCode($admin);
    $wrongCode = $validCode === '000000' ? '111111' : '000000';

    livewire(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')
        ->set('data.multiFactor.app.code', $wrongCode)
        ->call('authenticate')
        ->assertHasErrors(['data.multiFactor.app.code']);

    assertGuest('admin');
});

it('accepts a recovery code once in place of the authenticator code', function (): void {
    $provider = AppAuthentication::make()->recoverable();
    $admin = admin();
    $admin->saveAppAuthenticationSecret($provider->generateSecret());

    $codes = $provider->generateRecoveryCodes();
    $provider->saveRecoveryCodes($admin, $codes);

    livewire(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')
        ->set('data.multiFactor.app.useRecoveryCode', true)
        ->set('data.multiFactor.app.recoveryCode', $codes[0])
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(Filament::getUrl());

    assertAuthenticatedAs($admin, 'admin');

    // A used code is burned.
    expect($provider->verifyRecoveryCode($codes[0], $admin->refresh()))->toBeFalse();
});

it('emails a code to an admin who uses email authentication', function (): void {
    Notification::fake();

    $admin = admin();
    $admin->toggleEmailAuthentication(true);

    $component = livewire(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertNoRedirect();

    assertGuest('admin');

    $code = null;

    Notification::assertSentTo($admin, VerifyEmailAuthentication::class, function (VerifyEmailAuthentication $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    $component
        ->set('data.multiFactor.email_code.code', $code)
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(Filament::getUrl());

    assertAuthenticatedAs($admin, 'admin');
});

it('stores multi-factor secrets encrypted and never serialises them', function (): void {
    $provider = AppAuthentication::make();
    $admin = admin();
    $secret = $provider->generateSecret();
    $admin->saveAppAuthenticationSecret($secret);

    $stored = $admin->getConnection()->table('admins')->where('id', $admin->id)->value('app_authentication_secret');

    expect($stored)->not->toBe($secret)
        ->and($admin->refresh()->getAppAuthenticationSecret())->toBe($secret)
        ->and($admin->toArray())->not->toHaveKeys(['app_authentication_secret', 'app_authentication_recovery_codes', 'password']);
});
