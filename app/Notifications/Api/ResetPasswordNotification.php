<?php

declare(strict_types=1);

namespace App\Notifications\Api;

use Illuminate\Auth\Notifications\ResetPassword;
use Override;

/**
 * The password reset email for end users. The link opens the frontend,
 * which collects the new password and posts it to the API with the token.
 */
final class ResetPasswordNotification extends ResetPassword
{
    /**
     * @param  mixed  $notifiable
     */
    #[Override]
    protected function resetUrl($notifiable): string
    {
        return config('ghostdash.frontend_url').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
