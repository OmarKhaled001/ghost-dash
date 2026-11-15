<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActivityLog;
use App\Enums\AuthActivity;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes authentication events to the activity log in one shape for both
 * guards: who, which guard, from where.
 */
final readonly class AuthActivityLogger
{
    public function __construct(private Request $request) {}

    /**
     * Events such as PasswordReset and Verified carry a user but no guard.
     */
    public static function guardFor(?Model $user): ?string
    {
        return match (true) {
            $user instanceof Admin => 'admin',
            $user instanceof Model => 'web',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(AuthActivity $event, ?Model $user, ?string $guard = null, array $properties = []): void
    {
        $logger = activity(ActivityLog::Authentication)
            ->event($event->value)
            ->withProperties([
                'guard' => $guard ?? self::guardFor($user),
                'ip' => $this->request->ip(),
                'user_agent' => Str::limit((string) $this->request->userAgent(), 500),
                ...$properties,
            ]);

        // The person signing in is both who did it and what it happened to.
        // A failed attempt for an unknown address has neither.
        $user instanceof Model
            ? $logger->performedOn($user)->causedBy($user)
            : $logger->causedByAnonymous();

        $logger->log($event->value);
    }
}
