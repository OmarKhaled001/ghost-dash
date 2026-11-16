<?php

declare(strict_types=1);

namespace App\Providers;

use App\Exceptions\ApiExceptionRenderer;
use App\Listeners\LogAuthenticationActivity;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class ApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    /**
     * One named limiter per endpoint, so each can be tuned, and so that
     * exhausting one (hammering login) does not lock a client out of the
     * others (logging out, resetting a password).
     */
    private function configureRateLimiters(): void
    {
        // Guest endpoints are keyed by IP, plus the email where there is
        // one, so that one address cannot be attacked from a single machine
        // while a shared office IP is not blocked for everybody.
        RateLimiter::for('auth.register', fn (Request $request): Limit => $this->limit(5, 'register|'.$request->ip()));

        // The second, wider limit is per IP alone. The first can be stepped
        // around by spelling the address differently on every attempt, and
        // does nothing against one password tried on many accounts.
        RateLimiter::for('auth.login', fn (Request $request): array => [
            $this->limit(5, 'login|'.$this->email($request).'|'.$request->ip(), lockout: true),
            $this->limit(20, 'login|'.$request->ip(), lockout: true),
        ]);

        RateLimiter::for('auth.forgot-password', fn (Request $request): array => [
            $this->limit(3, 'forgot-password|'.$this->email($request).'|'.$request->ip()),
            $this->limit(10, 'forgot-password|'.$request->ip()),
        ]);

        RateLimiter::for('auth.reset-password', fn (Request $request): Limit => $this->limit(5, 'reset-password|'.$request->ip()));

        RateLimiter::for('auth.verify-email', fn (Request $request): Limit => $this->limit(6, 'verify-email|'.$request->ip()));

        // Authenticated endpoints are keyed by user.
        RateLimiter::for('auth.verification-notification', fn (Request $request): Limit => $this->limit(3, 'verification-notification|'.$this->user($request)));

        RateLimiter::for('auth.me', fn (Request $request): Limit => $this->limit(60, 'me|'.$this->user($request)));

        RateLimiter::for('auth.password', fn (Request $request): Limit => $this->limit(5, 'password|'.$this->user($request)));

        RateLimiter::for('auth.logout', fn (Request $request): Limit => $this->limit(30, 'logout|'.$this->user($request)));

        RateLimiter::for('auth.logout-all', fn (Request $request): Limit => $this->limit(10, 'logout-all|'.$this->user($request)));

        // Public and read-only, so generous; it is keyed by IP like the
        // other guest endpoints.
        RateLimiter::for('settings', fn (Request $request): Limit => $this->limit(120, 'settings|'.$request->ip()));
    }

    private function limit(int $perMinute, string $key, bool $lockout = false): Limit
    {
        return Limit::perMinute($perMinute)
            ->by($key)
            ->response(function (Request $request, array $headers) use ($lockout, $key): Response {
                // Recorded in the activity log, like a panel lockout, but
                // once per minute: every further refused request would
                // otherwise let an anonymous client add a row at will.
                if ($lockout && Cache::add('lockout-logged|'.sha1($key), true, 60)) {
                    $request->attributes->set(LogAuthenticationActivity::GUARD_ATTRIBUTE, 'sanctum');
                    event(new Lockout($request));
                }

                return resolve(ApiExceptionRenderer::class)->render(
                    new TooManyRequestsHttpException(headers: $headers),
                );
            });
    }

    /**
     * The address as the database will compare it. The column's collation
     * ignores case and accents, so "Víctim@" and "victim@" reach the same
     * account and must share one counter.
     */
    private function email(Request $request): string
    {
        $email = $request->input('email');

        // This runs before validation, so the field can be anything.
        return is_string($email) ? Str::lower(Str::ascii($email)) : '';
    }

    private function user(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }
}
