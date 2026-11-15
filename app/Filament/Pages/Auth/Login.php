<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Listeners\LogAuthenticationActivity;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Lockout;

final class Login extends BaseLogin
{
    /**
     * Filament throttles the form with its own rate limiter, which never
     * dispatches Laravel's Lockout event. This is the one place every
     * throttled attempt passes through, so the event is raised here and the
     * activity log can record lockouts for admins the same way it does for
     * API users.
     */
    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        // Lockout carries only the request, and this one is a Livewire call
        // whose payload does not expose the form fields.
        request()->attributes->set(LogAuthenticationActivity::GUARD_ATTRIBUTE, Filament::getAuthGuard());
        request()->attributes->set(LogAuthenticationActivity::EMAIL_ATTRIBUTE, $this->data['email'] ?? null);

        event(new Lockout(request()));

        return parent::getRateLimitedNotification($exception);
    }
}
