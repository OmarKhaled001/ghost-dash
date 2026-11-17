<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The sign-in providers end users can use through the API. The value is
 * what appears in the URL (/api/v1/auth/social/google).
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Facebook = 'facebook';
    case Github = 'github';
    case X = 'x';
    case Linkedin = 'linkedin';

    /**
     * The Laravel Socialite driver for this provider.
     */
    public function driver(): string
    {
        return match ($this) {
            self::Linkedin => 'linkedin-openid',
            default => $this->value,
        };
    }

    public function label(): string
    {
        return __('settings/socialite.providers.'.$this->value);
    }
}
