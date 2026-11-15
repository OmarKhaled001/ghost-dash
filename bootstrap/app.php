<?php

declare(strict_types=1);

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocaleFromHeader;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        // Outside local, a request is only answered for APP_URL's host, its
        // subdomains and TRUSTED_HOSTS. Absolute URLs are built from the
        // Host header, so without this a forged one ends up in the password
        // reset link that is mailed to an admin.
        $middleware->trustHosts(at: static fn (): array => config('ghostdash.trusted_hosts', []));

        // First in the API stack, so that validation errors and the 429
        // from a rate limiter are already in the client's language.
        $middleware->api(prepend: [SetLocaleFromHeader::class]);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // There is no "login" route to send a guest to. Browsers go to the
        // panel login; API clients get a 401 from the exception renderer.
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*')
            ? null
            : '/'.config('ghostdash.panel.path').'/login');

        // Laravel hoists every "authenticate" middleware ahead of ordinary
        // ones, wherever a route lists them. A deactivated admin has to be
        // signed out before the panel's Authenticate middleware looks at
        // them, otherwise they get a 403 instead of the login page.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureAdminIsActive::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        // Everything under /api answers with the API envelope, whatever
        // went wrong and whatever Accept header the client sent.
        $exceptions->render(function (Throwable $exception, Request $request) {
            // An HttpResponseException already carries a finished response
            // (the rate limiters throw one holding the 429 envelope).
            // Returning null lets Laravel send it as it is.
            if (! $request->is('api/*') || $exception instanceof HttpResponseException) {
                return null;
            }

            return resolve(ApiExceptionRenderer::class)->render($exception);
        });
    })->create();
