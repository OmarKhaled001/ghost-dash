<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\UserAuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RegistrationController extends Controller
{
    public function store(RegisterRequest $request, UserAuthService $auth): JsonResponse
    {
        $result = $auth->register($request->account(), $request->deviceName());

        return ApiResponse::success([
            'user' => UserResource::make($result['user'])->resolve($request),
            ...$result['token']->toArray(),
        ], __('api.messages.registered'), 201);
    }
}
