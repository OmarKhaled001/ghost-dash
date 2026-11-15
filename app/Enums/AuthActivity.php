<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Events recorded in the authentication log, for both guards.
 */
enum AuthActivity: string
{
    case Registered = 'registered';
    case Login = 'login';
    case Logout = 'logout';
    case FailedLogin = 'failed_login';
    case Lockout = 'lockout';
    case PasswordReset = 'password_reset';
    case EmailVerified = 'email_verified';
    case MfaEnabled = 'mfa_enabled';
    case MfaDisabled = 'mfa_disabled';
}
