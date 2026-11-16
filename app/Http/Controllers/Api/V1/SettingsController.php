<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PublicSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SettingsController extends Controller
{
    public function show(PublicSettingsService $settings): JsonResponse
    {
        return ApiResponse::success($settings->all());
    }
}
