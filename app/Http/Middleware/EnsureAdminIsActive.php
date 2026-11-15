<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminIsActive
{
    /**
     * Sign out an admin who was deactivated while they were logged in.
     *
     * The session is destroyed here and the request carries on as a guest,
     * so the panel's own Authenticate middleware produces the redirect. That
     * keeps the behaviour identical for full page loads and Livewire calls.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Filament::auth();
        $admin = $guard->user();

        if ($admin instanceof Admin && ! $admin->is_active) {
            $guard->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            Notification::make()
                ->title(__('admin.auth.deactivated.title'))
                ->body(__('admin.auth.deactivated.body'))
                ->danger()
                ->persistent()
                ->send();
        }

        return $next($request);
    }
}
