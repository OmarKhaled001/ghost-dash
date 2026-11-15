<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class RedirectTelescopeGuestsToLogin
{
    /**
     * Telescope answers everyone it does not recognise with 403. Someone who
     * is simply not signed in should be sent to the panel login instead, and
     * brought back here afterwards.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local') || Auth::guard('admin')->check()) {
            return $next($request);
        }

        // API calls made by Telescope's own frontend cannot follow a login
        // redirect; they fall through to the 403 from Telescope's gate.
        if ($request->expectsJson()) {
            return $next($request);
        }

        return redirect()->guest(Filament::getPanel(Admin::PANEL)->getLoginUrl());
    }
}
