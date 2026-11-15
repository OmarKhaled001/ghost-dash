<?php

declare(strict_types=1);

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Token authentication for end users. Every route has its own named rate
| limiter (app/Providers/ApiServiceProvider.php) and every response,
| errors included, uses the envelope from App\Support\ApiResponse.
|
| A breaking change gets a new prefix (v2) next to this one, not an edit.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [RegistrationController::class, 'store'])
            ->middleware('throttle:auth.register')
            ->name('register');

        Route::post('login', [TokenController::class, 'store'])
            ->middleware('throttle:auth.login')
            ->name('login');

        Route::post('forgot-password', [PasswordController::class, 'forgot'])
            ->middleware('throttle:auth.forgot-password')
            ->name('forgot-password');

        Route::post('reset-password', [PasswordController::class, 'reset'])
            ->middleware('throttle:auth.reset-password')
            ->name('reset-password');

        // Opened from an email, so there is no token to send. The signature
        // proves the link came from this application and has not expired.
        Route::get('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:auth.verify-email'])
            ->whereNumber('id')
            ->name('verify-email');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [ProfileController::class, 'show'])
                ->middleware(['ability:'.TokenAbility::AccountRead->value, 'throttle:auth.me'])
                ->name('me');

            Route::put('password', [PasswordController::class, 'update'])
                ->middleware(['ability:'.TokenAbility::AccountWrite->value, 'throttle:auth.password'])
                ->name('password');

            Route::post('email/verification-notification', [EmailVerificationController::class, 'resend'])
                ->middleware(['ability:'.TokenAbility::AccountWrite->value, 'throttle:auth.verification-notification'])
                ->name('verification-notification');

            // Revoking itself needs no ability: a token must always be able
            // to give up its own access.
            Route::post('logout', [TokenController::class, 'destroy'])
                ->middleware('throttle:auth.logout')
                ->name('logout');

            // Revoking the others is a change to the account, which a
            // read-only token has no business making.
            Route::post('logout-all', [TokenController::class, 'destroyAll'])
                ->middleware(['ability:'.TokenAbility::AccountWrite->value, 'throttle:auth.logout-all'])
                ->name('logout-all');
        });
    });
});
