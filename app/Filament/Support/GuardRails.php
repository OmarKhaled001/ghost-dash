<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Exceptions\GuardRailViolation;
use App\Models\Admin;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use LogicException;

/**
 * Glue between the panel and the services: who is acting, and what to show
 * when a service refuses a write.
 */
final class GuardRails
{
    public static function actor(): Admin
    {
        $admin = Filament::auth()->user();

        if (! $admin instanceof Admin) {
            throw new LogicException('The panel is only reachable by authenticated admins.');
        }

        return $admin;
    }

    /**
     * Run a service write. A refusal becomes a notification instead of an
     * error page, and the caller learns whether the write happened.
     *
     * @param  Closure(): mixed  $write
     */
    public static function attempt(Closure $write): bool
    {
        try {
            $write();

            return true;
        } catch (GuardRailViolation $violation) {
            self::notify($violation);

            return false;
        }
    }

    /**
     * For form pages: show why the save was refused and stop it, leaving
     * the form as the admin filled it in.
     */
    public static function halt(GuardRailViolation $violation): never
    {
        self::notify($violation);

        throw new Halt;
    }

    private static function notify(GuardRailViolation $violation): void
    {
        Notification::make()
            ->title(__('admin.guard_rails.title'))
            ->body($violation->getMessage())
            ->danger()
            ->send();
    }
}
