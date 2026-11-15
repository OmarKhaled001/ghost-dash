<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\AuthenticatedRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\UserAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TokenController extends Controller
{
    /**
     * Sign in: exchange credentials for a token.
     */
    public function store(LoginRequest $request, UserAuthService $auth): JsonResponse
    {
        $result = $auth->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->deviceName(),
            $request->abilities(),
        );

        return ApiResponse::success([
            'user' => UserResource::make($result['user'])->resolve($request),
            ...$result['token']->toArray(),
        ], __('api.messages.logged_in'));
    }

    /**
     * Sign out: revoke the token this request was made with.
     */
    public function destroy(AuthenticatedRequest $request, UserAuthService $auth): JsonResponse
    {
        $auth->logout($request->authenticatedUser());

        return ApiResponse::success(message: __('api.messages.logged_out'));
    }

    /**
     * Sign out everywhere: revoke every token of the user.
     */
    public function destroyAll(AuthenticatedRequest $request, UserAuthService $auth): JsonResponse
    {
        $auth->logoutEverywhere($request->authenticatedUser());

        return ApiResponse::success(message: __('api.messages.logged_out_everywhere'));
    }
}
