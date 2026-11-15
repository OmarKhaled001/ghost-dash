<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuthActivity;
use App\Support\AuthActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;

/**
 * Records sign-in related events for admins and users alike. Laravel
 * discovers each handle* method from its type hint.
 */
final readonly class LogAuthenticationActivity
{
    /**
     * Request attributes a caller may set before dispatching Lockout, which
     * carries nothing but the request.
     */
    public const string GUARD_ATTRIBUTE = 'ghostdash.auth.guard';

    public const string EMAIL_ATTRIBUTE = 'ghostdash.auth.email';

    public function __construct(private AuthActivityLogger $logger) {}

    public function handleRegistered(Registered $event): void
    {
        $this->logger->log(AuthActivity::Registered, $this->model($event->user));
    }

    public function handleLogin(Login $event): void
    {
        $this->logger->log(AuthActivity::Login, $this->model($event->user), $event->guard);
    }

    public function handleLogout(Logout $event): void
    {
        $user = $this->model($event->user);

        // Logging out an already expired session fires the event with no user.
        if (! $user instanceof Model) {
            return;
        }

        $this->logger->log(AuthActivity::Logout, $user, $event->guard);
    }

    public function handleFailed(Failed $event): void
    {
        $this->logger->log(AuthActivity::FailedLogin, $this->model($event->user), $event->guard, [
            // Only the identifier. The credentials array also holds the
            // password that was tried, which must never be written anywhere.
            'email' => $event->credentials['email'] ?? null,
        ]);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->logger->log(
            AuthActivity::Lockout,
            null,
            $event->request->attributes->get(self::GUARD_ATTRIBUTE),
            ['email' => $event->request->attributes->get(self::EMAIL_ATTRIBUTE) ?? $event->request->input('email')],
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->logger->log(AuthActivity::PasswordReset, $this->model($event->user));
    }

    public function handleVerified(Verified $event): void
    {
        $this->logger->log(AuthActivity::EmailVerified, $this->model($event->user));
    }

    private function model(mixed $user): ?Model
    {
        return $user instanceof Model ? $user : null;
    }
}
