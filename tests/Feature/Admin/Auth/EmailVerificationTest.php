<?php

declare(strict_types=1);

use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;

it('sends unverified admins to the verification prompt', function (): void {
    actingAsAdmin(admin(['email_verified_at' => null]));

    get(panelUrl())->assertRedirect(panelUrl('email-verification/prompt'));
});

it('lets verified admins through', function (): void {
    actingAsAdmin();

    get(panelUrl())->assertOk();
});

it('verifies an admin through the signed panel link', function (): void {
    $admin = actingAsAdmin(admin(['email_verified_at' => null]));

    get(Filament::getPanel('admin')->getVerifyEmailUrl($admin))->assertRedirect();

    expect($admin->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered verification link', function (): void {
    $admin = actingAsAdmin(admin(['email_verified_at' => null]));

    $url = Filament::getPanel('admin')->getVerifyEmailUrl($admin);

    get(preg_replace('/signature=[^&]+/', 'signature=forged', $url))->assertForbidden();

    expect($admin->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('sends the panel verification link from the model', function (): void {
    Notification::fake();

    $admin = admin(['email_verified_at' => null]);
    $admin->sendEmailVerificationNotification();

    Notification::assertSentTo($admin, VerifyEmail::class, fn (VerifyEmail $notification): bool => str_contains($notification->url, panelUrl('email-verification/verify')));
});

it('shows the profile page to a signed-in admin', function (): void {
    actingAsAdmin();

    get(panelUrl('profile'))->assertOk();
});
