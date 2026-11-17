<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\SocialProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SocialLoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\UserAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SocialLoginController extends Controller
{
    /**
     * Exchange a provider's access token for a token of ours.
     */
    public function store(SocialLoginRequest $request, SocialProvider $provider, UserAuthService $auth): JsonResponse
    {
        $result = $auth->loginWithProvider(
            $provider,
            $request->string('access_token')->toString(),
            $request->deviceName(),
        );

        return ApiResponse::success([
            'user' => UserResource::make($result['user'])->resolve($request),
            ...$result['token']->toArray(),
        ], __($result['created'] ? 'api.messages.social_registered' : 'api.messages.logged_in'), $result['created'] ? 201 : 200);
    }
}
