<?php

declare(strict_types=1);

namespace App\Notifications\Api;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Override;

/**
 * The verification email for end users.
 *
 * A subclass rather than VerifyEmail::createUrlUsing(), because that hook is
 * static and would change the link for every notifiable in the application,
 * admins included.
 */
final class VerifyEmailNotification extends VerifyEmail
{
    /**
     * @param  mixed  $notifiable
     */
    #[Override]
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'api.v1.auth.verify-email',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1((string) $notifiable->getEmailForVerification()),
            ],
        );
    }
}
