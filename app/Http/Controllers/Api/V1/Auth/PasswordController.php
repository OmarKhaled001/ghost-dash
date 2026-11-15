<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\UpdatePasswordRequest;
use App\Services\UserAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class PasswordController extends Controller
{
    public function forgot(ForgotPasswordRequest $request, UserAuthService $auth): JsonResponse
    {
        $auth->sendPasswordResetLink($request->string('email')->toString());

        // The same answer whether or not the address has an account.
        return ApiResponse::success(message: __('api.messages.password_reset_link_sent'));
    }

    public function reset(ResetPasswordRequest $request, UserAuthService $auth): JsonResponse
    {
        $auth->resetPassword($request->credentials());

        return ApiResponse::success(message: __('api.messages.password_reset'));
    }

    public function update(UpdatePasswordRequest $request, UserAuthService $auth): JsonResponse
    {
        $auth->updatePassword($request->authenticatedUser(), $request->string('password')->toString());

        return ApiResponse::success(message: __('api.messages.password_updated'));
    }
}
