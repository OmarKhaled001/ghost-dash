<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AuthenticatedRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ProfileController extends Controller
{
    public function show(AuthenticatedRequest $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => UserResource::make($request->authenticatedUser())->resolve($request),
        ]);
    }
}
